<?php

declare(strict_types=1);

namespace App\DataObjects;

use App\Enums\PaymentStatus;

/**
 * ما استخلصناه من خطّاف المزوّد بعد التحقق من توقيعه.
 *
 * signatureVerified ليست معلومة تقريرية: إن كانت false لا تتقدّم حالة أي
 * طلب، ويُسجَّل الحدث فقط. انظر PaymentService::handleWebhook
 */
final readonly class WebhookResult
{
    public function __construct(
        public bool $signatureVerified,
        public ?string $gatewayPaymentId,
        public ?PaymentStatus $status,
        public ?float $amount,
        public string $event,
        public array $payload,
        public ?string $gatewayReference = null,
        public ?string $failureReason = null,
    ) {}
}
