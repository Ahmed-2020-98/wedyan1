<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationItem extends Model
{
    protected $fillable = [
        'reservation_id', 'painting_id', 'dimension_preset_id',
        'title', 'description', 'width', 'height', 'depth', 'unit',
        'preferred_colors', 'reference_image_path', 'price',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'depth' => 'decimal:2',
            'price' => 'decimal:2',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function painting(): BelongsTo
    {
        return $this->belongsTo(Painting::class);
    }

    public function dimensionPreset(): BelongsTo
    {
        return $this->belongsTo(DimensionPreset::class);
    }
}
