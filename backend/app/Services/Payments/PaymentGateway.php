<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\DataObjects\PaymentIntent;
use App\DataObjects\WebhookResult;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * العقد الذي يفصل المنصة عن مزوّد الدفع.
 *
 * لا شيء خارج مجلد Payments يعرف اسم مزوّد بعينه؛ الشيفرة تطلب «بوابة»
 * ويقرّر GatewayResolver أيّها. ولذلك تغيير المزوّد لاحقاً يعني إضافة صنف
 * واحد يحقّق هذه الواجهة وتغيير PAYMENT_GATEWAY في البيئة.
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * ينشئ العملية لدى المزوّد ويعيد وجهة إعادة التوجيه.
     *
     * @param  array{returnUrl: string, description: string}  $context
     */
    public function createPayment(Payment $payment, array $context): PaymentIntent;

    /**
     * يستجوب المزوّد عن الحالة الحقيقية.
     *
     * تُستخدم للمزامنة حين يتأخّر الخطّاف أو يضيع — فلا يبقى طلب مدفوع
     * عالقاً في «بانتظار الدفع» لأن رسالة واحدة لم تصل.
     */
    public function verify(Payment $payment): WebhookResult;

    /** يتحقق من توقيع الخطّاف ثم يستخلص محتواه. */
    public function parseWebhook(Request $request): WebhookResult;

    public function refund(Payment $payment, float $amount): bool;
}
