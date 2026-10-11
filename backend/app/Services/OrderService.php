<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Availability;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Painting;
use App\Models\Payment;
use App\Models\ShippingRate;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderStatusChangedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OrderService
{
    public function __construct(private readonly CartService $carts) {}

    /**
     * يحوّل السلة إلى طلب.
     *
     * كل شيء داخل معاملة واحدة مع قفل صفوف اللوحات. السبب المباشر أن كل
     * لوحة قطعة واحدة: لو أتمّ زبونان الطلب في اللحظة نفسها، القفل يجعل
     * الثاني يقرأ المخزون بعد خصمه فيفشل بوضوح — بدلاً من بيع اللوحة
     * مرّتين واكتشاف ذلك عند الشحن.
     *
     * @param  array<string, mixed>  $shipping
     */
    public function createFromCart(User $user, Cart $cart, array $shipping, ?ShippingRate $rate): Order
    {
        return DB::transaction(function () use ($user, $cart, $shipping, $rate): Order {
            $cart->load(['items.painting', 'coupon']);

            if ($cart->items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => ['سلّتك فارغة.']]);
            }

            $paintingIds = $cart->items->pluck('painting_id')->all();

            /** @var array<int, Painting> $locked */
            $locked = Painting::whereIn('id', $paintingIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($cart->items as $item) {
                $painting = $locked[$item->painting_id] ?? null;

                if ($painting === null || ! $painting->isPurchasable() || $painting->stock_quantity < $item->quantity) {
                    throw ValidationException::withMessages([
                        'cart' => ['«' . ($painting?->title ?? 'لوحة') . '» لم تعد متاحة. حدّث سلّتك.'],
                    ]);
                }
            }

            $totals = $this->carts->totals($cart, $rate);

            $order = Order::create([
                'order_number' => $this->generateNumber(),
                'user_id' => $user->id,
                'status' => OrderStatus::PaymentPending,
                'payment_status' => PaymentStatus::Pending,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount'],
                'shipping_total' => $totals['shipping'],
                'tax_total' => $totals['tax'],
                'grand_total' => $totals['total'],
                'currency' => config('platform.currency'),
                'coupon_id' => $cart->coupon_id,
                'coupon_code' => $cart->coupon?->code,
                'shipping_name' => $shipping['recipient_name'],
                'shipping_phone' => $shipping['phone'],
                'shipping_country' => $shipping['country'],
                'shipping_city' => $shipping['city'],
                'shipping_district' => $shipping['district'] ?? null,
                'shipping_street' => $shipping['street'],
                'shipping_postal_code' => $shipping['postal_code'] ?? null,
                'shipping_notes' => $shipping['notes'] ?? null,
                'customer_email' => $shipping['email'] ?? $user->email,
                'customer_phone' => $shipping['phone'],
                'shipping_rate_id' => $rate?->id,
            ]);

            foreach ($cart->items as $item) {
                $painting = $locked[$item->painting_id];
                $unit = $painting->effectivePrice();

                $order->items()->create([
                    'painting_id' => $painting->id,
                    // لقطة مجمّدة: العنوان والسعر والمقاس وقت الشراء.
                    // تغيير سعر اللوحة لاحقاً يجب ألّا يغيّر فاتورة صدرت.
                    'title' => $painting->title,
                    'sku' => $painting->sku,
                    'image_path' => $painting->mainImage?->path,
                    'dimensions' => [
                        'width' => (float) $painting->width,
                        'height' => (float) $painting->height,
                        'depth' => $painting->depth === null ? null : (float) $painting->depth,
                        'unit' => $painting->unit,
                    ],
                    'unit_price' => $unit,
                    'quantity' => $item->quantity,
                    'line_total' => round($unit * $item->quantity, 2),
                ]);

                $remaining = $painting->stock_quantity - $item->quantity;

                $painting->update([
                    'stock_quantity' => $remaining,
                    // «محجوزة» لا «مباعة»: البيع لم يكتمل حتى يصل الدفع.
                    // لو فشل الدفع تعود متاحة — انظر releaseStock
                    'availability' => $remaining > 0 ? Availability::Available : Availability::Reserved,
                ]);
            }

            if ($cart->coupon !== null) {
                CouponUsage::create([
                    'coupon_id' => $cart->coupon_id,
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'discount_amount' => $totals['discount'],
                ]);

                $cart->coupon->increment('used_count');
            }

            $this->carts->clear($cart);

            return $order->load('items');
        });
    }

    /** تُستدعى من PaymentService بعد خطّاف موثَّق فقط. */
    public function markPaid(Order $order, Payment $payment): void
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            return;
        }

        $order->update([
            'payment_status' => PaymentStatus::Paid,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);

        foreach ($order->items as $item) {
            $painting = $item->painting;

            if ($painting === null) {
                continue;
            }

            $painting->increment('sold_count', $item->quantity);

            if ($painting->stock_quantity <= 0) {
                $painting->update(['availability' => Availability::Sold]);
            }
        }

        $order->user->notify(new OrderPlacedNotification($order));
    }

    /** يعيد المخزون عند الإلغاء أو فشل الدفع. */
    public function releaseStock(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            foreach ($order->items as $item) {
                $painting = Painting::whereKey($item->painting_id)->lockForUpdate()->first();

                if ($painting === null) {
                    continue;
                }

                $painting->update([
                    'stock_quantity' => $painting->stock_quantity + $item->quantity,
                    'availability' => Availability::Available,
                ]);
            }
        });
    }

    /**
     * تغيير الحالة عبر جدول الانتقالات المسموحة.
     *
     * الانتقال يُتحقق منه هنا لا في الواجهة: لوحة التحكم تستطيع إرسال أي
     * قيمة، وطلب ينتقل من «ملغى» إلى «تم الشحن» يجب أن يُرفض.
     */
    public function transition(Order $order, OrderStatus $target, ?string $trackingNumber = null): Order
    {
        if (! $order->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => [sprintf(
                    'لا يمكن الانتقال من «%s» إلى «%s».',
                    $order->status->label(),
                    $target->label(),
                )],
            ]);
        }

        $changes = ['status' => $target];

        match ($target) {
            OrderStatus::Shipped => $changes = [...$changes, 'shipped_at' => now(), 'tracking_number' => $trackingNumber ?? $order->tracking_number],
            OrderStatus::Delivered => $changes = [...$changes, 'delivered_at' => now()],
            OrderStatus::Cancelled => $changes = [...$changes, 'cancelled_at' => now()],
            default => null,
        };

        $order->update($changes);

        if ($target === OrderStatus::Cancelled) {
            $this->releaseStock($order);
        }

        $order->user->notify(new OrderStatusChangedNotification($order));

        return $order->refresh();
    }

    private function generateNumber(): string
    {
        return 'WD-' . now()->format('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
