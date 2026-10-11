<?php

declare(strict_types=1);

namespace App\Services\Payments;

use InvalidArgumentException;

final class GatewayResolver
{
    /** @var array<string, class-string<PaymentGateway>> */
    private const DRIVERS = [
        'fake' => FakeGateway::class,
        'paymob' => PaymobGateway::class,
    ];

    public function resolve(?string $driver = null): PaymentGateway
    {
        $driver ??= (string) config('services.payment.driver', 'fake');

        if (! isset(self::DRIVERS[$driver])) {
            throw new InvalidArgumentException("بوابة دفع غير معروفة: {$driver}");
        }

        return app(self::DRIVERS[$driver]);
    }

    /** @return list<string> */
    public function available(): array
    {
        return array_keys(self::DRIVERS);
    }
}
