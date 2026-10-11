<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manage;

use App\Enums\Availability;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Api\V1\Manage\Concerns\ScopesToArtist;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Painting;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DashboardController extends Controller
{
    use ScopesToArtist;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $paintings = $this->scopeForUser(Painting::query(), $user);

        // الإيراد من الطلبات المدفوعة فقط. احتساب الطلبات المعلّقة يضخّم
        // الرقم ويجعل لوحة التحكم تكذب على صاحبها.
        $revenue = (float) Order::where('payment_status', PaymentStatus::Paid->value)->sum('grand_total');

        $monthly = Order::where('payment_status', PaymentStatus::Paid->value)
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS month, SUM(grand_total) AS total, COUNT(*) AS orders")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'data' => [
                'paintings' => [
                    'total' => (clone $paintings)->count(),
                    'available' => (clone $paintings)->where('availability', Availability::Available->value)->count(),
                    'sold' => (clone $paintings)->where('availability', Availability::Sold->value)->count(),
                    'reserved' => (clone $paintings)->where('availability', Availability::Reserved->value)->count(),
                    'inProduction' => (clone $paintings)->where('availability', Availability::InProduction->value)->count(),
                ],
                'orders' => [
                    'total' => Order::count(),
                    'awaitingPayment' => Order::where('payment_status', PaymentStatus::Pending->value)->count(),
                    'toPrepare' => Order::whereIn('status', [OrderStatus::Paid->value, OrderStatus::Processing->value])->count(),
                    'shipped' => Order::where('status', OrderStatus::Shipped->value)->count(),
                    'delivered' => Order::where('status', OrderStatus::Delivered->value)->count(),
                ],
                'reservations' => [
                    'total' => Reservation::count(),
                    'pending' => Reservation::whereIn('status', [
                        ReservationStatus::Pending->value,
                        ReservationStatus::UnderReview->value,
                    ])->count(),
                    'inProduction' => Reservation::where('status', ReservationStatus::InProduction->value)->count(),
                    'awaitingPayment' => Reservation::whereIn('status', [
                        ReservationStatus::Accepted->value,
                        ReservationStatus::DepositPending->value,
                    ])->count(),
                ],
                'revenue' => [
                    'total' => Money::of($revenue)->toArray(),
                    'thisMonth' => Money::of((float) Order::where('payment_status', PaymentStatus::Paid->value)
                        ->where('created_at', '>=', now()->startOfMonth())->sum('grand_total'))->toArray(),
                ],
                'customers' => [
                    'total' => User::role('customer')->count(),
                    'newThisMonth' => User::role('customer')->where('created_at', '>=', now()->startOfMonth())->count(),
                ],
                'charts' => [
                    'monthlyRevenue' => $monthly->map(fn ($row) => [
                        'month' => $row->month,
                        'revenue' => (float) $row->total,
                        'orders' => (int) $row->orders,
                    ])->values(),
                    'bestSellers' => (clone $paintings)->where('sold_count', '>', 0)
                        ->orderByDesc('sold_count')->limit(5)
                        ->get(['id', 'title', 'sold_count'])
                        ->map(fn ($p) => ['title' => $p->title, 'sold' => $p->sold_count])->values(),
                ],
                'recentOrders' => Order::with('user:id,name')->latest()->limit(5)->get()
                    ->map(fn (Order $o) => [
                        'orderNumber' => $o->order_number,
                        'customer' => $o->user?->name,
                        'total' => Money::of($o->grand_total, $o->currency)->toArray(),
                        'statusLabel' => $o->status->label(),
                        'createdAt' => $o->created_at?->toIso8601String(),
                    ])->values(),
            ],
        ]);
    }
}
