<?php

declare(strict_types=1);

namespace Tests;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * الأدوار والصلاحيات مزروعة قبل كل اختبار.
     *
     * spatie تُخزّن الصلاحيات في الذاكرة المؤقتة، وتركها بين الاختبارات
     * يجعل اختباراً يرث أدوار اختبار سابق — فتنجح فحوص الصلاحيات لسبب خاطئ.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (in_array(\Illuminate\Foundation\Testing\RefreshDatabase::class, class_uses_recursive($this), true)) {
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            $this->seed(RoleSeeder::class);
        }
    }

    /**
     * ينسى الحارس المُحلَّل بين طلبين داخل الاختبار الواحد.
     *
     * في الإنتاج كل طلب HTTP يحصل على نسخة تطبيق جديدة، فلا يبقى مستخدم
     * محلول في الذاكرة. أما الاختبارات فتتشارك الحاوية، فيظلّ المستخدم
     * المُحلَّل من الطلب السابق موجوداً وتبدو الرموز الملغاة صالحة.
     *
     * كل اختبار يتحقق من إلغاء رمز يجب أن يستدعيها قبل الطلب التالي.
     */
    protected function forgetAuthenticatedUser(): void
    {
        $this->app['auth']->forgetGuards();
    }
}
