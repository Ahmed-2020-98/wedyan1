<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Artist = 'artist';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'مدير',
            self::Artist => 'فنان',
            self::Customer => 'زبون',
        };
    }
}
