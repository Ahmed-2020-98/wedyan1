<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Review */
final class ReviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            // الاسم الأول فقط في العرض العام — لا داعي لنشر الاسم الكامل.
            'authorName' => $this->whenLoaded('user', fn () => explode(' ', (string) $this->user->name)[0]),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($i) => $i->url())),
            'painting' => $this->whenLoaded('painting', fn () => [
                'title' => $this->painting->title,
                'slug' => $this->painting->slug,
            ]),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
