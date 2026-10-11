<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'description', 'type', 'value', 'min_order_amount',
        'max_discount_amount', 'starts_at', 'expires_at',
        'usage_limit', 'usage_limit_per_user', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /** هل الكوبون صالح زمنياً وضمن حدّ الاستخدام الكلّي؟ */
    public function isCurrentlyValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return ! ($this->usage_limit !== null && $this->used_count >= $this->usage_limit);
    }

    /**
     * قيمة الخصم على مبلغ معيّن، مقيَّدة بالسقف وبالمبلغ نفسه.
     *
     * القيد الأخير مقصود: خصم أكبر من قيمة الطلب يعني إجمالياً سالباً،
     * وهو ما يرفضه المزوّد ويُنتج طلباً عالقاً.
     */
    public function discountFor(float $subtotal): float
    {
        $discount = $this->type === CouponType::Percentage
            ? $subtotal * ((float) $this->value / 100)
            : (float) $this->value;

        if ($this->max_discount_amount !== null) {
            $discount = min($discount, (float) $this->max_discount_amount);
        }

        return round(min($discount, $subtotal), 2);
    }
}
