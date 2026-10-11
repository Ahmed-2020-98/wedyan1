<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentKind: string
{
    case Full = 'full';
    case Deposit = 'deposit';
    case Balance = 'balance';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'دفعة كاملة',
            self::Deposit => 'عربون',
            self::Balance => 'المبلغ المتبقّي',
        };
    }
}
