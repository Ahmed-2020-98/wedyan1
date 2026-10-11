<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DimensionPreset extends Model
{
    use HasFactory;

    protected $fillable = ['label', 'width', 'height', 'depth', 'unit', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'depth' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
