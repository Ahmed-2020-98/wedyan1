<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Models\CartItem;
use App\Models\Painting;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CartController extends Controller
{
    public function __construct(private readonly CartService $carts) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->carts->forUser($request->user());

        // التنظيف قبل العرض: اللوحة قطعة واحدة وقد تُباع بينما هي في سلة
        // شخص آخر. الأفضل أن يعرف الآن لا بعد إدخال بطاقته.
        $removed = $this->carts->pruneUnavailable($cart);

        /*
         | إعادة التحميل قبل حساب الإجماليات لا بعده.
         | pruneUnavailable يحذف من قاعدة البيانات، لكن مجموعة العناصر
         | المحمَّلة في الذاكرة تبقى كما هي — فتُحتسب الإجماليات على لوحة
         | أُزيلت للتوّ، ويرى الزبون مبلغاً لا يطابق سلّته.
         */
        $cart = $cart->fresh(['items.painting.mainImage', 'coupon']);

        return response()->json([
            'data' => new CartResource($cart, $this->carts->totals($cart)),
            'removed' => $removed,
        ]);
    }

    public function add(Request $request): JsonResponse
    {
        $data = $request->validate([
            'painting_id' => ['required', 'integer', 'exists:paintings,id'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ]);

        $cart = $this->carts->forUser($request->user());
        $painting = Painting::published()->findOrFail($data['painting_id']);

        $this->carts->add($cart, $painting, (int) ($data['quantity'] ?? 1));

        return $this->show($request);
    }

    public function updateItem(Request $request, CartItem $item): JsonResponse
    {
        $cart = $this->carts->forUser($request->user());

        abort_unless($item->cart_id === $cart->id, 403);

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:10']]);

        $this->carts->updateQuantity($cart, $item, (int) $data['quantity']);

        return $this->show($request);
    }

    public function removeItem(Request $request, CartItem $item): JsonResponse
    {
        $cart = $this->carts->forUser($request->user());

        abort_unless($item->cart_id === $cart->id, 403);

        $item->delete();

        return $this->show($request);
    }

    public function applyCoupon(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);

        $cart = $this->carts->forUser($request->user());
        $this->carts->applyCoupon($cart, $data['code'], $request->user());

        return $this->show($request);
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        $this->carts->removeCoupon($this->carts->forUser($request->user()));

        return $this->show($request);
    }

    public function clear(Request $request): JsonResponse
    {
        $this->carts->clear($this->carts->forUser($request->user()));

        return $this->show($request);
    }
}
