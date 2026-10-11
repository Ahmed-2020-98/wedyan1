<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manage\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * لوحة التحكم واحدة للفنان وللإدارة، والفرق بينهما نطاق البيانات لا شاشات
 * منفصلة: المدير يرى كل شيء، والفنان يرى ما يخصّ أعماله.
 *
 * وضع القصر هنا — في مكان واحد مشترك — أأمن من تكراره في كل دالة، لأن
 * نسيانه مرّة واحدة يكشف بيانات فنان آخر عند فتح المنصة لعدّة فنانين.
 */
trait ScopesToArtist
{
    protected function scopeForUser(Builder $query, User $user, string $column = 'artist_id'): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where($column, $user->id);
    }
}
