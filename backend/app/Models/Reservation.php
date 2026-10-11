<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reservation_number', 'user_id', 'artist_id', 'status',
        'price', 'deposit_percentage', 'deposit_amount', 'paid_amount', 'currency',
        'customer_note', 'artist_note', 'rejection_reason',
        'progress_percentage', 'due_date',
        'shipping_name', 'shipping_phone', 'shipping_country', 'shipping_city',
        'shipping_district', 'shipping_street', 'shipping_postal_code', 'tracking_number',
        'reviewed_at', 'accepted_at', 'production_started_at',
        'completed_at', 'delivered_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'price' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_date' => 'date',
            'reviewed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'production_started_at' => 'datetime',
            'completed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reservation_number';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'artist_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ReservationUpdate::class)->latest();
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /*
    |--------------------------------------------------------------------------
    | المبالغ
    |--------------------------------------------------------------------------
    */

    /** المتبقّي بعد ما دُفع فعلاً. */
    public function remainingAmount(): float
    {
        if ($this->price === null) {
            return 0.0;
        }

        return round(max(0, (float) $this->price - (float) $this->paid_amount), 2);
    }

    public function isFullyPaid(): bool
    {
        return $this->price !== null && $this->remainingAmount() <= 0.0;
    }

    /**
     * المبلغ المستحقّ الآن.
     *
     * قبل بدء الإنتاج المستحقّ هو العربون؛ وبعد اكتمال اللوحة يصبح المتبقّي.
     * وهذا هو سبب وجود دفعتين لا حقل مبلغ واحد.
     */
    public function amountDueNow(): float
    {
        if ($this->price === null) {
            return 0.0;
        }

        $paid = (float) $this->paid_amount;

        if ($paid <= 0.0) {
            return round(min((float) ($this->deposit_amount ?? $this->price), (float) $this->price), 2);
        }

        return $this->remainingAmount();
    }

    /** مجموع ما سُدِّد فعلياً حسب جدول الدفعات — مصدر الحقيقة عند أي تسوية. */
    public function settledAmount(): float
    {
        return (float) $this->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->sum('amount');
    }
}
