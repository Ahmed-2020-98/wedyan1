<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار الدفع',
            self::Processing => 'قيد المعالجة',
            self::Paid => 'مدفوع',
            self::Failed => 'فشل',
            self::Cancelled => 'ملغى',
            self::Refunded => 'مسترجع',
            self::PartiallyRefunded => 'مسترجع جزئياً',
        };
    }

    public function isSettled(): bool
    {
        return in_array($this, [self::Paid, self::Refunded, self::PartiallyRefunded], true);
    }
}
