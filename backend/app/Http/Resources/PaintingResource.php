<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * بطاقة اللوحة في قوائم المعرض. مختصرة عمداً: صفحة المعرض تعرض ٢٤ بطاقة،
 * وإرسال الوصف الكامل ومعرض الصور لكل واحدة يضاعف حجم الرد بلا فائدة.
 *
 * @mixin \App\Models\Painting
 */
// ليست final لأن PaintingDetailResource ترث منها وتضيف حقول صفحة اللوحة.
class PaintingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'shortDescription' => $this->short_description,

            'price' => Money::of($this->price)->toArray(),
            'effectivePrice' => Money::of($this->effectivePrice())->toArray(),
            'hasDiscount' => $this->hasDiscount(),

            'type' => $this->painting_type->value,
            'typeLabel' => $this->painting_type->label(),
            'availability' => $this->availability->value,
            'availabilityLabel' => $this->availability->label(),

            'isPurchasable' => $this->isPurchasable(),
            'isReservable' => $this->isReservable(),
            'isFeatured' => $this->is_featured,

            'dimensions' => $this->width === null ? null : [
                'width' => (float) $this->width,
                'height' => (float) $this->height,
                'depth' => $this->depth === null ? null : (float) $this->depth,
                'unit' => $this->unit,
            ],

            'rating' => [
                'average' => (float) $this->rating_avg,
                'count' => $this->rating_count,
            ],

            'category' => new CategoryResource($this->whenLoaded('category')),
            'mainImage' => new PaintingImageResource($this->whenLoaded('mainImage')),
        ];
    }
}
