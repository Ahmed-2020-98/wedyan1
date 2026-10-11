<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PaintingImage */
final class PaintingImageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url(),
            // الواجهة تختار النسخة حسب المقاس المعروض عبر srcset،
            // فلا تُحمَّل صورة بعرض 1800 بكسل داخل بطاقة عرضها 400.
            'thumb' => $this->variantUrl('thumb'),
            'medium' => $this->variantUrl('medium'),
            'large' => $this->variantUrl('large'),
            'alt' => $this->alt_text,
            'isMain' => $this->is_main,
        ];
    }
}
