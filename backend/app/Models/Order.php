<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number', 'user_id', 'status', 'payment_status',
        'subtotal', 'discount_total', 'shipping_total', 'tax_total', 'grand_total', 'currency',
        'coupon_id', 'coupon_code',
        'shipping_name', 'shipping_phone', 'shipping_country', 'shipping_city',
        'shipping_district', 'shipping_street', 'shipping_postal_code', 'shipping_notes',
        'customer_email', 'customer_phone',
        'shipping_rate_id', 'tracking_number',
        'paid_at', 'shipped_at', 'delivered_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function shippingRate(): BelongsTo
    {
        return $this->belongsTo(ShippingRate::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /** آخر دفعة ناجحة، وهي المرجع في أي استرجاع. */
    public function successfulPayment(): ?Payment
    {
        return $this->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->latest('paid_at')
            ->first();
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [
            OrderStatus::Pending,
            OrderStatus::PaymentPending,
            OrderStatus::Paid,
            OrderStatus::Processing,
        ], true);
    }
}
