<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShippingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ShippingZone::with('rates')->orderBy('sort_order')->get()->map(fn (ShippingZone $z) => [
                'id' => $z->id,
                'name' => $z->name,
                'countries' => $z->countries,
                'isActive' => $z->is_active,
                'rates' => $z->rates->map(fn (ShippingRate $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'cost' => Money::of($r->cost)->toArray(),
                    'freeShippingThreshold' => $r->free_shipping_threshold === null
                        ? null : Money::of($r->free_shipping_threshold)->toArray(),
                    'minDays' => $r->min_days,
                    'maxDays' => $r->max_days,
                    'isActive' => $r->is_active,
                ]),
            ]),
        ]);
    }

    public function storeZone(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'countries' => ['required', 'array', 'min:1'],
            'countries.*' => ['string', 'max:2'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        return response()->json(['data' => ShippingZone::create($data)], 201);
    }

    public function updateZone(Request $request, ShippingZone $zone): JsonResponse
    {
        $zone->update($request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'countries' => ['sometimes', 'array', 'min:1'],
            'countries.*' => ['string', 'max:2'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]));

        return response()->json(['data' => $zone->fresh()]);
    }

    public function destroyZone(ShippingZone $zone): JsonResponse
    {
        $zone->delete();

        return response()->json(['message' => 'حُذفت المنطقة.']);
    }

    public function storeRate(Request $request): JsonResponse
    {
        return response()->json(['data' => ShippingRate::create($this->rateRules($request))], 201);
    }

    public function updateRate(Request $request, ShippingRate $rate): JsonResponse
    {
        $rate->update($this->rateRules($request, false));

        return response()->json(['data' => $rate->fresh()]);
    }

    public function destroyRate(ShippingRate $rate): JsonResponse
    {
        $rate->delete();

        return response()->json(['message' => 'حُذفت طريقة الشحن.']);
    }

    /** @return array<string, mixed> */
    private function rateRules(Request $request, bool $creating = true): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'shipping_zone_id' => [$required, 'integer', 'exists:shipping_zones,id'],
            'name' => [$required, 'string', 'max:255'],
            'cost' => [$required, 'numeric', 'min:0'],
            'free_shipping_threshold' => ['nullable', 'numeric', 'min:0'],
            'min_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'max_days' => ['nullable', 'integer', 'min:0', 'max:365', 'gte:min_days'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
