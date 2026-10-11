<?php

declare(strict_types=1);

namespace App\Enums;

enum Availability: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case OutOfStock = 'out_of_stock';
    case InProduction = 'in_production';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'متاحة',
            self::Reserved => 'محجوزة',
            self::Sold => 'مباعة',
            self::OutOfStock => 'نفدت الكمية',
            self::InProduction => 'قيد التنفيذ',
            self::Unavailable => 'غير متاحة',
        };
    }

    /** هل يمكن إضافتها إلى السلة الآن؟ */
    public function isPurchasable(): bool
    {
        return $this === self::Available;
    }
}
