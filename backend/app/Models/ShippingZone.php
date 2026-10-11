<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingZone extends Model
{
    protected $fillable = ['name', 'countries', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'countries' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class);
    }

    public function coversCountry(string $code): bool
    {
        $countries = $this->countries ?? [];

        return in_array(strtoupper($code), $countries, true) || in_array('*', $countries, true);
    }
}
