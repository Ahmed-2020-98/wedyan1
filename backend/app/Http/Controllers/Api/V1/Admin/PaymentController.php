<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): JsonResponse
    {
        $query = Payment::with('user:id,name,email');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $payments = $query->latest()->paginate(20);

        return response()->json([
            'data' => $payments->getCollection()->map($this->present(...)),
            'meta' => [
                'currentPage' => $payments->currentPage(),
                'lastPage' => $payments->lastPage(),
                'perPage' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    /** سجلّ الأحداث الخام لعملية — مرجع أي نزاع محاسبي. */
    public function transactions(string $paymentNumber): JsonResponse
    {
        $payment = Payment::where('payment_number', $paymentNumber)->firstOrFail();

        return response()->json([
            'data' => $payment->transactions()->latest('id')->get()->map(fn ($t) => [
                'id' => $t->id,
                'event' => $t->event,
                'status' => $t->status,
                'amount' => $t->amount === null ? null : Money::of($t->amount)->toArray(),
                'signatureVerified' => $t->signature_verified,
                'createdAt' => $t->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function refund(Request $request, string $paymentNumber): JsonResponse
    {
        $payment = Payment::where('payment_number', $paymentNumber)->firstOrFail();

        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:' . $payment->refundableAmount()],
        ]);

        $ok = $this->payments->refund($payment, isset($data['amount']) ? (float) $data['amount'] : null);

        abort_unless($ok, 422, 'تعذّر تنفيذ الاسترجاع لدى المزوّد.');

        return response()->json([
            'data' => $this->present($payment->fresh()),
            'message' => 'نُفِّذ الاسترجاع.',
        ]);
    }

    /** @return array<string, mixed> */
    private function present(Payment $payment): array
    {
        return [
            'paymentNumber' => $payment->payment_number,
            'gateway' => $payment->gateway,
            'kind' => $payment->kind->value,
            'kindLabel' => $payment->kind->label(),
            'amount' => Money::of($payment->amount, $payment->currency)->toArray(),
            'refundedAmount' => Money::of($payment->refunded_amount, $payment->currency)->toArray(),
            'refundableAmount' => Money::of($payment->refundableAmount(), $payment->currency)->toArray(),
            'status' => $payment->status->value,
            'statusLabel' => $payment->status->label(),
            'failureReason' => $payment->failure_reason,
            'payableType' => class_basename($payment->payable_type),
            'customer' => $payment->user?->name,
            'paidAt' => $payment->paid_at?->toIso8601String(),
            'createdAt' => $payment->created_at?->toIso8601String(),
        ];
    }
}
