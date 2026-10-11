<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    protected $fillable = [
        'payment_number', 'user_id', 'payable_type', 'payable_id',
        'kind', 'gateway', 'gateway_payment_id', 'gateway_reference',
        'amount', 'refunded_amount', 'currency', 'status',
        'failure_reason', 'meta', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => PaymentKind::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'meta' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function refundableAmount(): float
    {
        return round((float) $this->amount - (float) $this->refunded_amount, 2);
    }
}
