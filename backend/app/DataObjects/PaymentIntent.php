<?php

declare(strict_types=1);

namespace App\DataObjects;

/**
 * نتيجة إنشاء عملية دفع لدى المزوّد: إلى أين نُرسل الزبون، وبأي مرجع
 * نتعرّف على العملية حين يعود الخطّاف.
 */
final readonly class PaymentIntent
{
    public function __construct(
        public string $redirectUrl,
        public ?string $gatewayPaymentId = null,
        public array $meta = [],
    ) {}
}
