<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\Category */
final class CategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'imageUrl' => $this->image_path ? Storage::url($this->image_path) : null,
            'isActive' => $this->is_active,
            'sortOrder' => $this->sort_order,
            'seo' => [
                'title' => $this->seo_title ?? $this->name,
                'description' => $this->seo_description ?? $this->description,
            ],
            'paintingsCount' => $this->whenCounted('paintings'),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
