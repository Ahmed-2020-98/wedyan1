<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $artist = User::create([
            'name' => 'وديان الفنانة',
            'email' => 'artist@wedyan.local',
            'phone' => '0500000001',
            'password' => 'password',
            'is_active' => true,
        ]);
        $artist->forceFill(['email_verified_at' => now()])->save();
        $artist->assignRole(Role::Artist->value);

        $admin = User::create([
            'name' => 'مدير المنصة',
            'email' => 'admin@wedyan.local',
            'phone' => '0500000002',
            'password' => 'password',
            'is_active' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::Admin->value);

        $customer = User::create([
            'name' => 'سارة العتيبي',
            'email' => 'customer@wedyan.local',
            'phone' => '0500000003',
            'password' => 'password',
            'is_active' => true,
        ]);
        $customer->forceFill(['email_verified_at' => now()])->save();
        $customer->assignRole(Role::Customer->value);

        Address::create([
            'user_id' => $customer->id,
            'label' => 'المنزل',
            'recipient_name' => 'سارة العتيبي',
            'phone' => '0500000003',
            'country' => 'SA',
            'city' => 'الرياض',
            'district' => 'حي الملقا',
            'street' => 'طريق أنس بن مالك، مبنى ١٢',
            'postal_code' => '13521',
            'is_default' => true,
        ]);

        $this->call([
            PlatformSeeder::class,
            CatalogSeeder::class,
        ]);
    }
}
