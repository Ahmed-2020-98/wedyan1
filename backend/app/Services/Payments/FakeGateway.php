<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\DataObjects\PaymentIntent;
use App\DataObjects\WebhookResult;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * بوابة وهمية للتطوير والعرض.
 *
 * وجودها مقصود لا مؤقّت: تعني أن أي مطوّر أو مراجع يستطيع تشغيل مسار
 * الشراء والحجز كاملاً — بما فيه الخطّاف وتغيّر حالة الطلب — دون حساب لدى
 * أي مزوّد ودون مفاتيح. وتُبقي الاختبارات الآلية مستقلّة عن الشبكة.
 *
 * تُرسل الزبون إلى صفحة محاكاة داخلية يختار فيها نجاح العملية أو فشلها،
 * فيسلك النظام المسار نفسه الذي يسلكه مع مزوّد حقيقي.
 */
final class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function createPayment(Payment $payment, array $context): PaymentIntent
    {
        $reference = 'FAKE-' . strtoupper(bin2hex(random_bytes(8)));

        return new PaymentIntent(
            redirectUrl: route('payments.simulator', [
                'payment' => $payment->payment_number,
                'return' => $context['returnUrl'],
            ]),
            gatewayPaymentId: $reference,
            meta: ['simulated' => true],
        );
    }

    public function verify(Payment $payment): WebhookResult
    {
        return new WebhookResult(
            signatureVerified: true,
            gatewayPaymentId: $payment->gateway_payment_id,
            status: $payment->status,
            amount: (float) $payment->amount,
            event: 'verify',
            payload: ['simulated' => true],
        );
    }

    /**
     * صفحة المحاكاة ترسل الحقول مباشرةً، ولا توقيع يُتحقق منه.
     *
     * المسار محمي على مستوى التوجيه: نقطة المحاكاة غير مسجّلة أصلاً حين
     * يكون السائق الفعّال غير fake — انظر routes/web.php
     */
    public function parseWebhook(Request $request): WebhookResult
    {
        $success = $request->boolean('success');

        return new WebhookResult(
            signatureVerified: true,
            gatewayPaymentId: (string) $request->input('gateway_payment_id'),
            status: $success ? PaymentStatus::Paid : PaymentStatus::Failed,
            amount: (float) $request->input('amount'),
            event: 'simulated.' . ($success ? 'paid' : 'failed'),
            payload: $request->all(),
            gatewayReference: (string) $request->input('gateway_payment_id'),
            failureReason: $success ? null : 'رفضت البطاقة العملية (محاكاة).',
        );
    }

    public function refund(Payment $payment, float $amount): bool
    {
        return true;
    }
}
