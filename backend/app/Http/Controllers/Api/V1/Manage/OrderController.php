<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manage;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Order::with(['items', 'user:id,name,email,phone']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q->where('order_number', 'like', $like)
                ->orWhere('shipping_name', 'like', $like)
                ->orWhere('customer_email', 'like', $like));
        }

        return OrderResource::collection($query->latest()->paginate(20));
    }

    public function show(string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)
            ->with(['items.painting', 'user', 'shippingRate'])
            ->firstOrFail();

        return response()->json(['data' => new OrderResource($order)]);
    }

    public function transition(Request $request, string $orderNumber): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string'],
            'tracking_number' => ['nullable', 'string', 'max:64'],
        ]);

        $target = OrderStatus::tryFrom($data['status']);
        abort_if($target === null, 422, 'حالة غير معروفة.');

        $order = Order::where('order_number', $orderNumber)->firstOrFail();
        $order = $this->orders->transition($order, $target, $data['tracking_number'] ?? null);

        return response()->json([
            'data' => new OrderResource($order->load(['items', 'user'])),
        ]);
    }

    /** الحالات المسموح الانتقال إليها — تبني منها الواجهة قائمتها. */
    public function transitions(string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        return response()->json([
            'data' => array_map(
                fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                $order->status->allowedTransitions(),
            ),
        ]);
    }
}
