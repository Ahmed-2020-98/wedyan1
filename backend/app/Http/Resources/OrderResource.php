<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\Order */
final class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'orderNumber' => $this->order_number,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'paymentStatus' => $this->payment_status->value,
            'paymentStatusLabel' => $this->payment_status->label(),
            'isCancellable' => $this->isCancellable(),

            'totals' => [
                'subtotal' => Money::of($this->subtotal, $this->currency)->toArray(),
                'discount' => Money::of($this->discount_total, $this->currency)->toArray(),
                'shipping' => Money::of($this->shipping_total, $this->currency)->toArray(),
                'tax' => Money::of($this->tax_total, $this->currency)->toArray(),
                'total' => Money::of($this->grand_total, $this->currency)->toArray(),
            ],
            'couponCode' => $this->coupon_code,

            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'title' => $item->title,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unitPrice' => Money::of($item->unit_price, $this->currency)->toArray(),
                'lineTotal' => Money::of($item->line_total, $this->currency)->toArray(),
                'dimensions' => $item->dimensions,
                'imageUrl' => $item->image_path ? Storage::url($item->image_path) : null,
                'paintingSlug' => $item->painting?->slug,
            ])),

            'shipping' => [
                'name' => $this->shipping_name,
                'phone' => $this->shipping_phone,
                'country' => $this->shipping_country,
                'city' => $this->shipping_city,
                'district' => $this->shipping_district,
                'street' => $this->shipping_street,
                'postalCode' => $this->shipping_postal_code,
                'notes' => $this->shipping_notes,
                'trackingNumber' => $this->tracking_number,
                'method' => $this->whenLoaded('shippingRate', fn () => $this->shippingRate?->name),
            ],

            'customer' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ]),

            'timeline' => [
                'placedAt' => $this->created_at?->toIso8601String(),
                'paidAt' => $this->paid_at?->toIso8601String(),
                'shippedAt' => $this->shipped_at?->toIso8601String(),
                'deliveredAt' => $this->delivered_at?->toIso8601String(),
                'cancelledAt' => $this->cancelled_at?->toIso8601String(),
            ],
        ];
    }
}
