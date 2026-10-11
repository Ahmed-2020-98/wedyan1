<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سطر سجلّ لا يُعدَّل. لذلك لا يوجد updated_at.
 */
class PaymentTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'payment_id', 'event', 'gateway', 'gateway_reference',
        'status', 'amount', 'signature_verified', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'signature_verified' => 'boolean',
            'amount' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
