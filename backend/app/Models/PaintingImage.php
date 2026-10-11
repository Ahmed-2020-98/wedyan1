<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PaintingImage extends Model
{
    protected $fillable = ['painting_id', 'path', 'variants', 'alt_text', 'is_main', 'sort_order'];

    protected function casts(): array
    {
        return [
            'variants' => 'array',
            'is_main' => 'boolean',
        ];
    }

    public function painting(): BelongsTo
    {
        return $this->belongsTo(Painting::class);
    }

    public function url(): string
    {
        return Storage::url($this->path);
    }

    /** رابط نسخة بعينها مع السقوط إلى الأصل إن لم تُولَّد. */
    public function variantUrl(string $variant): string
    {
        $path = $this->variants[$variant] ?? $this->path;

        return Storage::url($path);
    }
}
