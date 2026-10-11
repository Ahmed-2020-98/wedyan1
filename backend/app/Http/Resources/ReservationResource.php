<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Reservation */
final class ReservationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'reservationNumber' => $this->reservation_number,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'awaitsPayment' => $this->status->awaitsPayment() && $this->amountDueNow() > 0,
            'progressPercentage' => $this->progress_percentage,

            'price' => $this->price === null ? null : Money::of($this->price, $this->currency)->toArray(),
            'depositPercentage' => $this->deposit_percentage,
            'depositAmount' => $this->deposit_amount === null ? null : Money::of($this->deposit_amount, $this->currency)->toArray(),
            'paidAmount' => Money::of($this->paid_amount, $this->currency)->toArray(),
            'remainingAmount' => Money::of($this->remainingAmount(), $this->currency)->toArray(),
            'amountDueNow' => Money::of($this->amountDueNow(), $this->currency)->toArray(),
            'isFullyPaid' => $this->isFullyPaid(),

            'customerNote' => $this->customer_note,
            'artistNote' => $this->artist_note,
            'rejectionReason' => $this->rejection_reason,
            'dueDate' => $this->due_date?->toDateString(),

            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'title' => $item->title,
                'description' => $item->description,
                'dimensions' => $item->width === null ? null : [
                    'width' => (float) $item->width,
                    'height' => (float) $item->height,
                    'depth' => $item->depth === null ? null : (float) $item->depth,
                    'unit' => $item->unit,
                ],
                'preferredColors' => $item->preferred_colors,
                'paintingSlug' => $item->painting?->slug,
                'paintingTitle' => $item->painting?->title,
            ])),

            'updates' => $this->whenLoaded('updates', fn () => $this->updates
                ->where('is_visible_to_customer', true)
                ->map(fn ($u) => [
                    'title' => $u->title,
                    'note' => $u->note,
                    'imageUrl' => $u->imageUrl(),
                    'progressPercentage' => $u->progress_percentage,
                    'createdAt' => $u->created_at?->toIso8601String(),
                ])->values()),

            'customer' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ]),

            'shipping' => [
                'name' => $this->shipping_name,
                'phone' => $this->shipping_phone,
                'city' => $this->shipping_city,
                'district' => $this->shipping_district,
                'street' => $this->shipping_street,
                'trackingNumber' => $this->tracking_number,
            ],

            'timeline' => [
                'requestedAt' => $this->created_at?->toIso8601String(),
                'acceptedAt' => $this->accepted_at?->toIso8601String(),
                'productionStartedAt' => $this->production_started_at?->toIso8601String(),
                'completedAt' => $this->completed_at?->toIso8601String(),
                'deliveredAt' => $this->delivered_at?->toIso8601String(),
            ],
        ];
    }
}
