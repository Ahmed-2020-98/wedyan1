<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentKind;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly OrderService $orders,
        private readonly PaymentService $payments,
    ) {}

    /** خيارات الشحن المتاحة لبلد الزبون مع التكلفة الفعلية لسلّته. */
    public function shippingOptions(Request $request): JsonResponse
    {
        $country = strtoupper((string) $request->query('country', 'SA'));
        $cart = $this->carts->forUser($request->user());
        $totals = $this->carts->totals($cart);
        $net = $totals['subtotal'] - $totals['discount'];

        $zone = ShippingZone::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->first(fn (ShippingZone $z) => $z->coversCountry($country));

        $rates = $zone === null
            ? collect()
            : $zone->rates()->where('is_active', true)->get();

        return response()->json([
            'data' => $rates->map(fn (ShippingRate $rate) => [
                'id' => $rate->id,
                'name' => $rate->name,
                'cost' => Money::of($rate->costFor($net))->toArray(),
                'isFree' => $rate->costFor($net) <= 0,
                'freeShippingThreshold' => $rate->free_shipping_threshold === null
                    ? null
                    : Money::of($rate->free_shipping_threshold)->toArray(),
                'estimatedDays' => ['min' => $rate->min_days, 'max' => $rate->max_days],
            ])->values(),
            'zone' => $zone?->name,
        ]);
    }

    /**
     * ينشئ الطلب ويعيد وجهة الدفع.
     *
     * الطلب يُنشأ قبل الدفع لا بعده: نحتاج رقماً ثابتاً نربط به الخطّاف
     * حين يعود، ونحتاج حجز المخزون قبل إرسال الزبون إلى المزوّد.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^(\+?966|0)?5\d{8}$/'],
            'email' => ['sometimes', 'email', 'max:255'],
            'country' => ['required', 'string', 'size:2'],
            'city' => ['required', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'shipping_rate_id' => ['required', 'integer', 'exists:shipping_rates,id'],
        ], [
            'phone.regex' => 'رقم الجوال يجب أن يكون رقم جوال سعودي صحيح.',
        ]);

        $user = $request->user();
        $cart = $this->carts->forUser($user);
        $rate = ShippingRate::findOrFail($data['shipping_rate_id']);

        $order = $this->orders->createFromCart($user, $cart, $data, $rate);

        $result = $this->payments->initiate(
            payable: $order,
            amount: (float) $order->grand_total,
            kind: PaymentKind::Full,
            returnUrl: rtrim((string) config('platform.urls.web'), '/')
                . '/checkout/result?order=' . $order->order_number,
            description: 'طلب ' . $order->order_number,
        );

        return response()->json([
            'data' => [
                'order' => new OrderResource($order->load(['items', 'shippingRate'])),
                'payment' => [
                    'paymentNumber' => $result['payment']->payment_number,
                    'redirectUrl' => $result['intent']->redirectUrl,
                ],
            ],
        ], 201);
    }
}
