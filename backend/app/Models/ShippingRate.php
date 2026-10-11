<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRate extends Model
{
    protected $fillable = [
        'shipping_zone_id', 'name', 'cost',
        'free_shipping_threshold', 'min_days', 'max_days', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'free_shipping_threshold' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    /** التكلفة الفعلية بعد احتساب عتبة الشحن المجاني. */
    public function costFor(float $subtotal): float
    {
        if ($this->free_shipping_threshold !== null
            && $subtotal >= (float) $this->free_shipping_threshold) {
            return 0.0;
        }

        return (float) $this->cost;
    }
}
