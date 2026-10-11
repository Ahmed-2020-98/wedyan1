<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Cart */
final class CartResource extends JsonResource
{
    /** @param array{subtotal: float, discount: float, shipping: float, tax: float, total: float, itemCount: int} $totals */
    public function __construct($resource, private readonly array $totals)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'unitPrice' => Money::of($item->painting?->effectivePrice())->toArray(),
                'lineTotal' => Money::of(($item->painting?->effectivePrice() ?? 0) * $item->quantity)->toArray(),
                'painting' => new PaintingResource($item->painting),
            ])->values(),
            'coupon' => $this->coupon === null ? null : [
                'code' => $this->coupon->code,
                'description' => $this->coupon->description,
            ],
            'totals' => [
                'subtotal' => Money::of($this->totals['subtotal'])->toArray(),
                'discount' => Money::of($this->totals['discount'])->toArray(),
                'shipping' => Money::of($this->totals['shipping'])->toArray(),
                'tax' => Money::of($this->totals['tax'])->toArray(),
                'total' => Money::of($this->totals['total'])->toArray(),
                'itemCount' => $this->totals['itemCount'],
            ],
        ];
    }
}
