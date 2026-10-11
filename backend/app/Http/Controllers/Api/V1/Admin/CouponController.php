<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CouponType;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CouponController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Coupon::withCount('usages')->latest()->get()->map($this->present(...)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $coupon = Coupon::create([...$data, 'code' => mb_strtoupper($data['code'])]);

        return response()->json(['data' => $this->present($coupon)], 201);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $data = $this->validated($request, $coupon);

        if (isset($data['code'])) {
            $data['code'] = mb_strtoupper($data['code']);
        }

        $coupon->update($data);

        return response()->json(['data' => $this->present($coupon->fresh())]);
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();

        return response()->json(['message' => 'حُذف الكوبون.']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $required = $coupon === null ? 'required' : 'sometimes';

        return $request->validate([
            'code' => [$required, 'string', 'max:64', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => [$required, Rule::enum(CouponType::class)],
            'value' => [$required, 'numeric', 'min:0.01'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function present(Coupon $coupon): array
    {
        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'description' => $coupon->description,
            'type' => $coupon->type->value,
            'typeLabel' => $coupon->type->label(),
            'value' => (float) $coupon->value,
            'displayValue' => $coupon->type === CouponType::Percentage
                ? ((float) $coupon->value) . '٪'
                : Money::of($coupon->value)->formatted(),
            'minOrderAmount' => $coupon->min_order_amount === null ? null : Money::of($coupon->min_order_amount)->toArray(),
            'maxDiscountAmount' => $coupon->max_discount_amount === null ? null : Money::of($coupon->max_discount_amount)->toArray(),
            'startsAt' => $coupon->starts_at?->toIso8601String(),
            'expiresAt' => $coupon->expires_at?->toIso8601String(),
            'usageLimit' => $coupon->usage_limit,
            'usageLimitPerUser' => $coupon->usage_limit_per_user,
            'usedCount' => $coupon->used_count,
            'isActive' => $coupon->is_active,
            'isCurrentlyValid' => $coupon->isCurrentlyValid(),
        ];
    }
}
