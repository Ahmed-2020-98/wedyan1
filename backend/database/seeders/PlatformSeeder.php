<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

final class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $ksa = ShippingZone::create([
            'name' => 'السعودية',
            'countries' => ['SA'],
            'sort_order' => 0,
        ]);

        $gulf = ShippingZone::create([
            'name' => 'دول الخليج',
            'countries' => ['AE', 'KW', 'QA', 'BH', 'OM'],
            'sort_order' => 1,
        ]);

        $world = ShippingZone::create([
            'name' => 'بقية العالم',
            'countries' => ['*'],
            'sort_order' => 2,
        ]);

        ShippingRate::create([
            'shipping_zone_id' => $ksa->id,
            'name' => 'شحن قياسي داخل السعودية',
            'cost' => 35,
            // اللوحات مرتفعة القيمة أصلاً، والشحن المجاني فوق ٢٠٠٠ ريال
            // يشجّع على اقتناء العمل الأكبر بدل التردّد بسبب ٣٥ ريالاً.
            'free_shipping_threshold' => 2000,
            'min_days' => 3,
            'max_days' => 6,
        ]);

        ShippingRate::create([
            'shipping_zone_id' => $ksa->id,
            'name' => 'شحن سريع',
            'cost' => 75,
            'min_days' => 1,
            'max_days' => 2,
        ]);

        ShippingRate::create([
            'shipping_zone_id' => $gulf->id,
            'name' => 'شحن دولي — الخليج',
            'cost' => 180,
            'free_shipping_threshold' => 5000,
            'min_days' => 5,
            'max_days' => 10,
        ]);

        ShippingRate::create([
            'shipping_zone_id' => $world->id,
            'name' => 'شحن دولي',
            'cost' => 420,
            'min_days' => 10,
            'max_days' => 21,
        ]);

        Coupon::create([
            'code' => 'WELCOME10',
            'description' => 'خصم ١٠٪ لأول عملية شراء',
            'type' => CouponType::Percentage,
            'value' => 10,
            'min_order_amount' => 500,
            'max_discount_amount' => 500,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addYear(),
            'usage_limit' => 500,
            'usage_limit_per_user' => 1,
        ]);

        Coupon::create([
            'code' => 'ART200',
            'description' => 'خصم ٢٠٠ ريال على الطلبات فوق ٢٠٠٠ ريال',
            'type' => CouponType::Fixed,
            'value' => 200,
            'min_order_amount' => 2000,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonths(6),
            'usage_limit' => 200,
            'usage_limit_per_user' => 2,
        ]);

        // كوبون منتهي — يُبقي مسار الرفض قابلاً للتجربة دون تعديل قاعدة البيانات.
        Coupon::create([
            'code' => 'EXPIRED',
            'description' => 'كوبون منتهي (للاختبار)',
            'type' => CouponType::Percentage,
            'value' => 50,
            'starts_at' => now()->subMonths(2),
            'expires_at' => now()->subMonth(),
        ]);

        foreach ([
            ['key' => 'store.name', 'value' => 'وديان', 'group' => 'general'],
            ['key' => 'store.tagline', 'value' => 'لوحات فنية أصلية', 'group' => 'general'],
            ['key' => 'store.email', 'value' => 'hello@wedyan.local', 'group' => 'general'],
            ['key' => 'store.phone', 'value' => '0500000000', 'group' => 'general'],
            ['key' => 'tax.enabled', 'value' => true, 'group' => 'tax'],
            ['key' => 'tax.percentage', 'value' => 15, 'group' => 'tax'],
            ['key' => 'reservation.default_deposit_percentage', 'value' => 50, 'group' => 'reservation'],
            ['key' => 'reviews.require_purchase', 'value' => true, 'group' => 'reviews'],
        ] as $setting) {
            Setting::create($setting);
        }
    }
}
