<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * حارس شامل على جدول المسارات.
 *
 * الاختبارات الأخرى تتحقق من نقاط بعينها؛ هذا يتحقق من أن **كل** مسار
 * يلمس بيانات مستخدم يحمل الحراسة المناسبة. الغرض منه أن يفشل حين يضيف
 * أحدهم نقطة جديدة داخل المجموعة الخطأ — وهي أسهل ثغرة تُرتكب بلا قصد.
 */
final class RouteProtectionTest extends TestCase
{
    use RefreshDatabase;

    /** بادئات لا يجوز أن تُفتح لزائر مجهول. */
    private const AUTHENTICATED_PREFIXES = [
        'api/v1/cart',
        'api/v1/wishlist',
        'api/v1/addresses',
        'api/v1/orders',
        'api/v1/reservations',
        'api/v1/checkout',
        'api/v1/account',
        'api/v1/notifications',
        'api/v1/manage',
        'api/v1/admin',
    ];

    /** بادئات تتطلّب دوراً فوق المصادقة. */
    private const ROLE_PREFIXES = [
        'api/v1/manage' => 'admin|artist',
        'api/v1/admin' => 'admin',
    ];

    private function matchesAny(string $uri, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($uri, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function test_every_user_data_route_requires_authentication(): void
    {
        $unprotected = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! $this->matchesAny($uri, self::AUTHENTICATED_PREFIXES)) {
                continue;
            }

            /*
             | gatherMiddleware يعيد الأسماء كما كُتبت في ملف المسارات
             | (auth:sanctum، active، role:…) لا أسماء الأصناف.
             */
            $middleware = array_map('strval', $route->gatherMiddleware());

            $hasAuth = in_array('auth:sanctum', $middleware, true);
            $hasActiveCheck = in_array('active', $middleware, true);

            if (! $hasAuth || ! $hasActiveCheck) {
                $unprotected[] = $uri . ' [' . implode(', ', array_map('strval', $middleware)) . ']';
            }
        }

        $this->assertSame([], $unprotected, "مسارات بلا حراسة كاملة:\n" . implode("\n", $unprotected));
    }

    public function test_dashboard_and_admin_routes_require_the_right_role(): void
    {
        $missing = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            foreach (self::ROLE_PREFIXES as $prefix => $expectedRoles) {
                if (! str_starts_with($uri, $prefix)) {
                    continue;
                }

                $hasRole = in_array("role:{$expectedRoles}", array_map('strval', $route->gatherMiddleware()), true);

                if (! $hasRole) {
                    $missing[] = "{$uri} (يتوقّع {$expectedRoles})";
                }
            }
        }

        $this->assertSame([], $missing, "مسارات بلا حارس دور:\n" . implode("\n", $missing));
    }

    /**
     * نقاط المصادقة العامة يجب أن تبقى محدودة المعدّل.
     * بلا ذلك تصبح صفحة الدخول أداة تخمين كلمات مرور بلا كلفة.
     */
    public function test_public_auth_endpoints_are_rate_limited(): void
    {
        $unlimited = [];

        $shouldThrottle = [
            'api/v1/auth/login',
            'api/v1/auth/register',
            'api/v1/auth/forgot-password',
            'api/v1/auth/reset-password',
            'api/v1/newsletter',
        ];

        foreach (Route::getRoutes() as $route) {
            if (! in_array($route->uri(), $shouldThrottle, true)) {
                continue;
            }

            $throttled = collect($route->gatherMiddleware())->contains(
                fn ($m) => is_string($m) && str_starts_with($m, 'throttle:'),
            );

            if (! $throttled) {
                $unlimited[] = $route->uri();
            }
        }

        $this->assertSame([], $unlimited, 'نقاط بلا حدّ معدّل: ' . implode(', ', $unlimited));
    }

    /** صفحة محاكاة الدفع يجب ألّا توجد إلا مع السائق fake. */
    public function test_the_payment_simulator_is_absent_for_real_gateways(): void
    {
        $this->assertTrue(Route::has('payments.simulator'), 'المحاكاة متوقّعة مع السائق fake.');

        config()->set('services.payment.driver', 'paymob');

        // نعيد تحميل ملف المسارات بالإعداد الجديد
        $fresh = new \Illuminate\Routing\RouteCollection;
        $original = Route::getRoutes();
        Route::setRoutes($fresh);
        require base_path('routes/web.php');

        $simulatorExists = collect(Route::getRoutes())->contains(
            fn ($r) => str_starts_with($r->uri(), 'payments/simulator'),
        );

        Route::setRoutes($original);

        $this->assertFalse($simulatorExists, 'المحاكاة يجب ألّا تُسجَّل مع مزوّد حقيقي.');
    }
}
