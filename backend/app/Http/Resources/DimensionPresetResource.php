<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DimensionPreset */
final class DimensionPresetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'width' => (float) $this->width,
            'height' => (float) $this->height,
            'depth' => $this->depth === null ? null : (float) $this->depth,
            'unit' => $this->unit,
            'priceModifier' => $this->whenPivotLoaded('painting_dimension_preset', fn () => Money::of($this->pivot->price_modifier)->toArray()),
        ];
    }
}
