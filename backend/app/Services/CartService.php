<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Painting;
use App\Models\ShippingRate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CartService
{
    public function forUser(User $user): Cart
    {
        return Cart::firstOrCreate(['user_id' => $user->id]);
    }

    public function add(Cart $cart, Painting $painting, int $quantity = 1): CartItem
    {
        if (! $painting->isPurchasable()) {
            throw ValidationException::withMessages([
                'painting' => ['هذه اللوحة غير متاحة للشراء المباشر.'],
            ]);
        }

        /*
         | اللوحة قطعة واحدة فريدة في الغالب، ولذلك الكمية مقيّدة بالمخزون
         | لا مفتوحة. طلب نسختين من عمل واحد لا معنى له هنا.
         */
        $quantity = max(1, min($quantity, $painting->stock_quantity));

        $item = $cart->items()->firstOrNew(['painting_id' => $painting->id]);
        $item->quantity = min($quantity, $painting->stock_quantity);
        $item->save();

        return $item;
    }

    public function updateQuantity(Cart $cart, CartItem $item, int $quantity): void
    {
        if ($quantity <= 0) {
            $item->delete();

            return;
        }

        $item->update(['quantity' => min($quantity, $item->painting->stock_quantity)]);
    }

    public function applyCoupon(Cart $cart, string $code, User $user): Coupon
    {
        $coupon = Coupon::whereRaw('LOWER(code) = ?', [mb_strtolower(trim($code))])->first();

        if ($coupon === null || ! $coupon->isCurrentlyValid()) {
            throw ValidationException::withMessages([
                'coupon' => ['هذا الكوبون غير صالح أو منتهي الصلاحية.'],
            ]);
        }

        $totals = $this->totals($cart);

        if ($coupon->min_order_amount !== null && $totals['subtotal'] < (float) $coupon->min_order_amount) {
            throw ValidationException::withMessages([
                'coupon' => ['الحد الأدنى لاستخدام هذا الكوبون ' . (float) $coupon->min_order_amount . ' ريال.'],
            ]);
        }

        if ($coupon->usage_limit_per_user !== null) {
            $used = $coupon->usages()->where('user_id', $user->id)->count();

            if ($used >= $coupon->usage_limit_per_user) {
                throw ValidationException::withMessages([
                    'coupon' => ['استخدمت هذا الكوبون العدد المسموح به.'],
                ]);
            }
        }

        $cart->update(['coupon_id' => $coupon->id]);

        return $coupon;
    }

    public function removeCoupon(Cart $cart): void
    {
        $cart->update(['coupon_id' => null]);
    }

    /**
     * يحسب إجماليات السلة.
     *
     * الترتيب مقصود ومطابق للممارسة المحاسبية السعودية:
     *   الإجمالي الفرعي → الخصم → الشحن → الضريبة على (الفرعي - الخصم + الشحن)
     *
     * احتساب الضريبة قبل الخصم يحمّل الزبون ضريبة مبلغ لم يدفعه.
     *
     * @return array{subtotal: float, discount: float, shipping: float, tax: float, total: float, itemCount: int}
     */
    public function totals(Cart $cart, ?ShippingRate $rate = null): array
    {
        $cart->loadMissing(['items.painting', 'coupon']);

        $subtotal = 0.0;
        $itemCount = 0;

        foreach ($cart->items as $item) {
            if ($item->painting === null) {
                continue;
            }

            $subtotal += $item->painting->effectivePrice() * $item->quantity;
            $itemCount += $item->quantity;
        }

        $subtotal = round($subtotal, 2);

        $discount = $cart->coupon !== null && $cart->coupon->isCurrentlyValid()
            ? $cart->coupon->discountFor($subtotal)
            : 0.0;

        $shipping = $rate !== null ? $rate->costFor($subtotal - $discount) : 0.0;

        $taxable = max(0, $subtotal - $discount + $shipping);
        $tax = config('platform.tax.enabled')
            ? round($taxable * ((float) config('platform.tax.percentage') / 100), 2)
            : 0.0;

        return [
            'subtotal' => $subtotal,
            'discount' => round($discount, 2),
            'shipping' => round($shipping, 2),
            'tax' => $tax,
            'total' => round($taxable + $tax, 2),
            'itemCount' => $itemCount,
        ];
    }

    /**
     * يزيل من السلة ما لم يعد قابلاً للشراء.
     *
     * تُستدعى قبل عرض السلة وقبل إتمام الطلب: اللوحة قطعة واحدة، وقد تُباع
     * بينما هي في سلة زبون آخر منذ أسبوع. الأفضل أن يكتشف ذلك في السلة لا
     * بعد إدخال بطاقته.
     *
     * @return list<string> عناوين ما أُزيل
     */
    public function pruneUnavailable(Cart $cart): array
    {
        $cart->loadMissing('items.painting');
        $removed = [];

        foreach ($cart->items as $item) {
            if ($item->painting === null || ! $item->painting->isPurchasable()) {
                $removed[] = $item->painting?->title ?? 'لوحة محذوفة';
                $item->delete();
            }
        }

        return $removed;
    }

    public function clear(Cart $cart): void
    {
        DB::transaction(function () use ($cart): void {
            $cart->items()->delete();
            $cart->update(['coupon_id' => null]);
        });
    }
}
