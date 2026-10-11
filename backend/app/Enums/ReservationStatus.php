<?php

declare(strict_types=1);

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case DepositPending = 'deposit_pending';
    case InProduction = 'in_production';
    case Completed = 'completed';
    case ReadyForDelivery = 'ready_for_delivery';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'طلب جديد',
            self::UnderReview => 'قيد المراجعة',
            self::Accepted => 'مقبول',
            self::Rejected => 'مرفوض',
            self::DepositPending => 'بانتظار العربون',
            self::InProduction => 'قيد التنفيذ',
            self::Completed => 'اكتملت اللوحة',
            self::ReadyForDelivery => 'جاهزة للتسليم',
            self::Cancelled => 'ملغى',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::UnderReview, self::Accepted, self::Rejected, self::Cancelled],
            self::UnderReview => [self::Accepted, self::Rejected, self::Cancelled],
            /*
             | القبول يفتح باب الدفع. المسار المعتاد يمرّ بـ DepositPending
             | حين يُرسَل الزبون إلى بوابة الدفع، لكن الانتقال المباشر إلى
             | الإنتاج مسموح أيضاً: حين يكون العربون ١٠٠٪ تصل دفعة واحدة
             | كاملة ولا معنى لحالة «بانتظار العربون» بعدها.
             */
            self::Accepted => [self::DepositPending, self::InProduction, self::Cancelled],
            self::DepositPending => [self::InProduction, self::Cancelled],
            self::InProduction => [self::Completed, self::Cancelled],
            self::Completed => [self::ReadyForDelivery],
            self::ReadyForDelivery => [],
            self::Rejected, self::Cancelled => [],
        };
    }

    /** هل ينتظر النظام دفعة من الزبون في هذه الحالة؟ */
    public function awaitsPayment(): bool
    {
        return in_array($this, [self::Accepted, self::DepositPending, self::Completed], true);
    }
}
