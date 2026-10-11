<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AccountController extends Controller
{
    /** إحصاءات لوحة الزبون. */
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();

        $active = [
            OrderStatus::Paid->value,
            OrderStatus::Processing->value,
            OrderStatus::Preparing->value,
            OrderStatus::Shipped->value,
        ];

        return response()->json([
            'data' => [
                'orders' => [
                    'total' => $user->orders()->count(),
                    'active' => $user->orders()->whereIn('status', $active)->count(),
                    'completed' => $user->orders()->where('status', OrderStatus::Delivered->value)->count(),
                ],
                'reservations' => [
                    'total' => $user->reservations()->count(),
                    'active' => $user->reservations()->whereNotIn('status', [
                        ReservationStatus::Rejected->value,
                        ReservationStatus::Cancelled->value,
                    ])->count(),
                    'inProduction' => $user->reservations()
                        ->where('status', ReservationStatus::InProduction->value)->count(),
                ],
                'wishlistCount' => $user->wishlistItems()->count(),
                'totalSpent' => Money::of(
                    (float) $user->orders()->where('payment_status', PaymentStatus::Paid->value)->sum('grand_total')
                )->toArray(),
                'unreadNotifications' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    /** سجلّ المدفوعات للزبون. */
    public function payments(Request $request): JsonResponse
    {
        $payments = $request->user()->payments()->with('payable')->latest()->paginate(20);

        return response()->json([
            'data' => $payments->getCollection()->map(fn ($p) => [
                'paymentNumber' => $p->payment_number,
                'amount' => Money::of($p->amount, $p->currency)->toArray(),
                'kindLabel' => $p->kind->label(),
                'status' => $p->status->value,
                'statusLabel' => $p->status->label(),
                'reference' => $p->payable?->order_number ?? $p->payable?->reservation_number,
                'paidAt' => $p->paid_at?->toIso8601String(),
                'createdAt' => $p->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'currentPage' => $payments->currentPage(),
                'lastPage' => $payments->lastPage(),
                'total' => $payments->total(),
            ],
        ]);
    }
}
