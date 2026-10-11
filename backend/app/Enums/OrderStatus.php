<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case PaymentPending = 'payment_pending';
    case Paid = 'paid';
    case Processing = 'processing';
    case Preparing = 'preparing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار المعالجة',
            self::PaymentPending => 'بانتظار الدفع',
            self::Paid => 'مدفوع',
            self::Processing => 'قيد المعالجة',
            self::Preparing => 'قيد التجهيز',
            self::Shipped => 'تم الشحن',
            self::Delivered => 'تم التسليم',
            self::Cancelled => 'ملغى',
            self::Refunded => 'مسترجع',
        };
    }

    /**
     * الحالات التي يجوز الانتقال إليها من الحالة الحالية.
     *
     * الانتقالات معرّفة هنا لا في الواجهة، لأن أي لوحة تحكم تستطيع إرسال أي
     * قيمة؛ التحقق يجب أن يقع في الخادم.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::PaymentPending, self::Paid, self::Cancelled],
            self::PaymentPending => [self::Paid, self::Cancelled],
            self::Paid => [self::Processing, self::Preparing, self::Cancelled, self::Refunded],
            self::Processing => [self::Preparing, self::Cancelled, self::Refunded],
            self::Preparing => [self::Shipped, self::Cancelled, self::Refunded],
            self::Shipped => [self::Delivered, self::Refunded],
            self::Delivered => [self::Refunded],
            self::Cancelled, self::Refunded => [],
        };
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
