<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;

/**
 * صفحة اللوحة الواحدة: كل ما في البطاقة، مضافاً إليه ما يُقنع بالشراء.
 *
 * @mixin \App\Models\Painting
 */
final class PaintingDetailResource extends PaintingResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),

            'description' => $this->description,
            'discountPrice' => $this->discount_price === null
                ? null
                : Money::of($this->discount_price)->toArray(),

            'stockQuantity' => $this->stock_quantity,
            'weight' => $this->weight === null ? null : (float) $this->weight,
            'material' => $this->material,
            'medium' => $this->medium,
            'frameInfo' => $this->frame_info,
            'style' => $this->style,
            'colors' => $this->colors ?? [],

            // إعدادات الحجز — تقرأها الواجهة لبناء نموذج الطلب وحساب العربون.
            'reservation' => $this->painting_type->isReservable() ? [
                'depositPercentage' => $this->deposit_percentage,
                'productionDays' => $this->production_days,
            ] : null,

            'seo' => [
                'title' => $this->seo_title ?? $this->title,
                'description' => $this->seo_description ?? $this->short_description,
            ],

            'artist' => [
                'name' => $this->whenLoaded('artist', fn () => $this->artist->name),
            ],

            'images' => PaintingImageResource::collection($this->whenLoaded('images')),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($t) => [
                'name' => $t->name,
                'slug' => $t->slug,
            ])),
            'dimensionPresets' => DimensionPresetResource::collection($this->whenLoaded('dimensionPresets')),

            'publishedAt' => $this->published_at?->toIso8601String(),
        ];
    }
}
