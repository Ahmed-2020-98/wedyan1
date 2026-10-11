<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ReservationUpdate extends Model
{
    protected $fillable = [
        'reservation_id', 'created_by', 'status_from', 'status_to',
        'title', 'note', 'image_path', 'progress_percentage', 'is_visible_to_customer',
    ];

    protected function casts(): array
    {
        return ['is_visible_to_customer' => 'boolean'];
    }

    public function scopeVisibleToCustomer(Builder $query): Builder
    {
        return $query->where('is_visible_to_customer', true);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path === null ? null : Storage::url($this->image_path);
    }
}
