<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * نقاط الدفع التي يستدعيها المزوّد أو المتصفح — خارج /api/v1 لأنها ليست
 * جزءاً من عقد الواجهة البرمجية ولا تُستهلك من Angular.
 */
final class PaymentWebhookController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * خطّاف المزوّد.
     *
     * يعيد 200 دائماً حتى مع التوقيع الفاسد. السبب عملي: المزوّدون يعيدون
     * المحاولة عند أي رمز خطأ، فردّ 4xx على حمولة مزوّرة يعني أن مهاجماً
     * يستطيع دفع المزوّد إلى قصفنا بإعادة الإرسال. الحدث يُسجَّل ويُهمَل.
     */
    public function handle(Request $request, string $gateway): JsonResponse
    {
        $result = $this->payments->handleWebhook($request, $gateway);

        return response()->json([
            'received' => true,
            'verified' => $result->signatureVerified,
        ]);
    }

    /**
     * صفحة محاكاة الدفع — لا تُسجَّل إلا حين يكون السائق fake.
     * انظر routes/web.php
     */
    public function simulator(Request $request, string $payment): View
    {
        $record = Payment::where('payment_number', $payment)->firstOrFail();

        return view('payments.simulator', [
            'payment' => $record,
            'returnUrl' => (string) $request->query('return', config('platform.urls.web')),
        ]);
    }

    public function simulate(Request $request, string $payment): RedirectResponse
    {
        $record = Payment::where('payment_number', $payment)->firstOrFail();
        $success = $request->boolean('success');

        // نمرّ بنفس مسار الخطّاف لا باختصار: الهدف أن يختبر العرض المسار
        // الحقيقي كاملاً بما فيه التسجيل وجلب الثبات.
        $this->payments->handleWebhook(
            $request->merge([
                'gateway_payment_id' => $record->gateway_payment_id,
                'amount' => (float) $record->amount,
                'success' => $success,
            ]),
            'fake',
        );

        $return = (string) $request->input('return_url', config('platform.urls.web'));

        return redirect()->away($return . (str_contains($return, '?') ? '&' : '?')
            . 'payment=' . ($success ? 'success' : 'failed'));
    }
}
