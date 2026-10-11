<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class RoleSeeder extends Seeder
{
    /**
     * الصلاحيات مذكورة صراحةً لا مولّدة.
     *
     * القائمة الصريحة تجعل مراجعة «من يستطيع ماذا» قراءةَ ملف واحد، وهو
     * أهمّ من اختصار بضعة أسطر.
     */
    public const PERMISSIONS = [
        'paintings.view', 'paintings.create', 'paintings.update', 'paintings.delete',
        'categories.manage', 'dimensions.manage',
        'orders.view', 'orders.update',
        'reservations.view', 'reservations.update',
        'payments.view', 'payments.refund',
        'coupons.manage', 'reviews.moderate',
        'banners.manage', 'shipping.manage', 'settings.manage',
        'users.manage', 'roles.manage',
        'reports.view',
    ];

    /** ما يملكه الفنان: كل ما يخصّ أعماله ومبيعاتها، دون إدارة المنصة. */
    public const ARTIST_PERMISSIONS = [
        'paintings.view', 'paintings.create', 'paintings.update', 'paintings.delete',
        'categories.manage', 'dimensions.manage',
        'orders.view', 'orders.update',
        'reservations.view', 'reservations.update',
        'reviews.moderate',
        'reports.view',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate(RoleEnum::Admin->value, 'web')
            ->syncPermissions(self::PERMISSIONS);

        Role::findOrCreate(RoleEnum::Artist->value, 'web')
            ->syncPermissions(self::ARTIST_PERMISSIONS);

        // الزبون لا يملك أي صلاحية إدارية؛ صلاحياته على بياناته تُحكم بالسياسات.
        Role::findOrCreate(RoleEnum::Customer->value, 'web');
    }
}
