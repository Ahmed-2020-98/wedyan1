<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\PaymentIntent;
use App\DataObjects\WebhookResult;
use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Services\Payments\GatewayResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class PaymentService
{
    public function __construct(
        private readonly GatewayResolver $gateways,
        private readonly OrderService $orders,
        private readonly ReservationService $reservations,
    ) {}

    /**
     * ينشئ دفعة لطلب أو حجز ويعيد وجهة الدفع.
     */
    public function initiate(
        Model $payable,
        float $amount,
        PaymentKind $kind,
        string $returnUrl,
        string $description,
    ): array {
        $gateway = $this->gateways->resolve();

        $payment = Payment::create([
            'payment_number' => $this->generateNumber(),
            'user_id' => $payable->user_id,
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->getKey(),
            'kind' => $kind,
            'gateway' => $gateway->name(),
            'amount' => round($amount, 2),
            'currency' => $payable->currency ?? config('platform.currency'),
            'status' => PaymentStatus::Pending,
        ]);

        $intent = $gateway->createPayment($payment, [
            'returnUrl' => $returnUrl,
            'description' => $description,
        ]);

        $payment->update([
            'gateway_payment_id' => $intent->gatewayPaymentId,
            'status' => PaymentStatus::Processing,
            'meta' => $intent->meta,
        ]);

        return ['payment' => $payment, 'intent' => $intent];
    }

    /**
     * يعالج خطّاف المزوّد.
     *
     * قاعدتان تحكمان هذه الدالة:
     *
     * ١) لا تتقدّم حالة أي طلب أو حجز ما لم يكن التوقيع صحيحاً. الحدث
     *    غير الموثَّق يُسجَّل ويُهمَل — لأن نقطة الخطّاف عامة على الإنترنت،
     *    ومن يعرف عنوانها يستطيع ادّعاء نجاح أي عملية.
     *
     * ٢) العملية جالبة للثبات (idempotent). المزوّدون يعيدون إرسال الخطّاف
     *    عند أي شكّ في التسليم، ودفعة وصلت مرّتين يجب ألّا تُرحّل الطلب
     *    مرّتين ولا تُنقص المخزون مرّتين.
     */
    public function handleWebhook(Request $request, string $gatewayName): WebhookResult
    {
        $gateway = $this->gateways->resolve($gatewayName);
        $result = $gateway->parseWebhook($request);

        $payment = $this->locatePayment($result);

        PaymentTransaction::create([
            'payment_id' => $payment?->id,
            'event' => $result->event,
            'gateway' => $gatewayName,
            'gateway_reference' => $result->gatewayReference ?? $result->gatewayPaymentId,
            'status' => $result->status?->value,
            'amount' => $result->amount,
            'signature_verified' => $result->signatureVerified,
            'payload' => $result->payload,
        ]);

        if (! $result->signatureVerified) {
            Log::warning('payment.webhook.signature_invalid', [
                'gateway' => $gatewayName,
                'reference' => $result->gatewayPaymentId,
            ]);

            return $result;
        }

        if ($payment === null) {
            Log::warning('payment.webhook.unknown_payment', [
                'gateway' => $gatewayName,
                'reference' => $result->gatewayPaymentId,
            ]);

            return $result;
        }

        $this->applyResult($payment, $result);

        return $result;
    }

    /** يزامن حالة دفعة عالقة باستجواب المزوّد مباشرةً. */
    public function synchronise(Payment $payment): Payment
    {
        if ($payment->status->isSettled()) {
            return $payment;
        }

        $result = $this->gateways->resolve($payment->gateway)->verify($payment);

        if ($result->signatureVerified) {
            $this->applyResult($payment, $result);
        }

        return $payment->refresh();
    }

    public function refund(Payment $payment, ?float $amount = null): bool
    {
        $amount ??= $payment->refundableAmount();

        if ($amount <= 0 || $amount > $payment->refundableAmount()) {
            return false;
        }

        $ok = $this->gateways->resolve($payment->gateway)->refund($payment, $amount);

        if ($ok) {
            $refunded = round((float) $payment->refunded_amount + $amount, 2);

            $payment->update([
                'refunded_amount' => $refunded,
                'status' => $refunded >= (float) $payment->amount
                    ? PaymentStatus::Refunded
                    : PaymentStatus::PartiallyRefunded,
            ]);
        }

        return $ok;
    }

    /**
     * يطبّق نتيجة موثَّقة على الدفعة وعلى ما تخصّه.
     */
    private function applyResult(Payment $payment, WebhookResult $result): void
    {
        DB::transaction(function () use ($payment, $result): void {
            /*
             | القفل هنا يمنع خطّافين متزامنين من دخول نفس الفرع.
             | lockForUpdate يُسلسلهما، والفحص التالي يجعل الثاني بلا أثر.
             */
            $fresh = Payment::whereKey($payment->getKey())->lockForUpdate()->first();

            if ($fresh === null || $fresh->status === PaymentStatus::Paid) {
                return;
            }

            if ($result->status !== PaymentStatus::Paid) {
                $fresh->update([
                    'status' => $result->status ?? PaymentStatus::Failed,
                    'failure_reason' => $result->failureReason,
                ]);

                return;
            }

            $fresh->update([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'gateway_reference' => $result->gatewayReference ?? $fresh->gateway_reference,
            ]);

            $payable = $fresh->payable;

            match (true) {
                $payable instanceof \App\Models\Order => $this->orders->markPaid($payable, $fresh),
                $payable instanceof \App\Models\Reservation => $this->reservations->recordPayment($payable, $fresh),
                default => null,
            };
        });
    }

    private function locatePayment(WebhookResult $result): ?Payment
    {
        return Payment::query()
            ->when(
                $result->gatewayReference !== null,
                fn ($q) => $q->where('payment_number', $result->gatewayReference),
            )
            ->orWhere('gateway_payment_id', $result->gatewayPaymentId)
            ->latest('id')
            ->first();
    }

    private function generateNumber(): string
    {
        return 'PAY-' . now()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }
}
