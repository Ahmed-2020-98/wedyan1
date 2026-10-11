<?php

declare(strict_types=1);

namespace App\Enums;

enum PaintingType: string
{
    /** عمل مكتمل يُشترى ويُشحن مباشرةً. */
    case ReadyMade = 'ready_made';

    /** عمل معروض كنموذج، يُطلب رسم نسخة منه بمقاس يختاره الزبون. */
    case Reservable = 'reservable';

    /** طلب مفتوح: يصف الزبون ما يريد ويُسعّره الفنان بعد المراجعة. */
    case CustomOrder = 'custom_order';

    public function label(): string
    {
        return match ($this) {
            self::ReadyMade => 'جاهزة',
            self::Reservable => 'قابلة للحجز',
            self::CustomOrder => 'طلب خاص',
        };
    }

    /** هل يمرّ هذا النوع بمسار الحجز بدل السلة؟ */
    public function isReservable(): bool
    {
        return $this !== self::ReadyMade;
    }
}
