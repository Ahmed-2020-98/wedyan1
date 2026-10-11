<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly PaymentService $payments,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()->orders()
            ->with(['items'])
            ->latest()
            ->paginate(10);

        return OrderResource::collection($orders);
    }

    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $order = $this->findOwned($request, $orderNumber);

        /*
         | مزامنة انتهازية: لو كان الطلب ما يزال بانتظار الدفع نستجوب
         | المزوّد. الخطّافات تضيع أحياناً، ولا يصحّ أن يرى الزبون طلباً
         | «بانتظار الدفع» وقد خُصم المبلغ من بطاقته فعلاً.
         */
        if ($order->payment_status->value === 'pending' || $order->payment_status->value === 'processing') {
            $payment = $order->payments()->latest('id')->first();

            if ($payment !== null) {
                $this->payments->synchronise($payment);
                $order->refresh();
            }
        }

        return response()->json([
            'data' => new OrderResource($order->load(['items.painting', 'shippingRate'])),
        ]);
    }

    public function cancel(Request $request, string $orderNumber): JsonResponse
    {
        $order = $this->findOwned($request, $orderNumber);

        abort_unless($order->isCancellable(), 422, 'لا يمكن إلغاء هذا الطلب في حالته الحالية.');

        $this->orders->transition($order, OrderStatus::Cancelled);

        return response()->json([
            'data' => new OrderResource($order->fresh(['items'])),
            'message' => 'أُلغي الطلب.',
        ]);
    }

    /** يقصر الوصول على طلبات صاحب الحساب — لا يكفي معرفة رقم الطلب. */
    private function findOwned(Request $request, string $orderNumber): Order
    {
        return $request->user()->orders()
            ->where('order_number', $orderNumber)
            ->firstOrFail();
    }
}
