<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'painting_id', 'title', 'sku', 'image_path',
        'dimensions', 'unit_price', 'quantity', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'dimensions' => 'array',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function painting(): BelongsTo
    {
        return $this->belongsTo(Painting::class);
    }
}
