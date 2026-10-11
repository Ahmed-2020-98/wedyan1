<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class MetaTest extends TestCase
{
    public function test_health_reports_ok_when_the_database_is_reachable(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'database' => 'ok',
            ]);
    }

    public function test_meta_exposes_the_settings_the_frontend_needs(): void
    {
        $this->getJson('/api/v1/meta')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'appName',
                    'locale',
                    'currency',
                    'tax' => ['enabled', 'percentage'],
                    'payment' => ['driver'],
                ],
            ])
            ->assertJsonPath('data.locale', 'ar');
    }

    /**
     * حارس انحدار: نقطة meta تُقرأ من متصفّح أي زائر، فأي تسريب لمفتاح
     * سرّي فيها يعني تسريبه للعالم. هذا الاختبار يفشل إن أُضيف مفتاح لاحقاً.
     */
    public function test_meta_never_leaks_gateway_secrets(): void
    {
        config([
            'services.paymob.secret_key' => 'sk_test_leaked_secret',
            'services.paymob.hmac_secret' => 'hmac_leaked_secret',
        ]);

        $body = $this->getJson('/api/v1/meta')->getContent();

        $this->assertStringNotContainsString('sk_test_leaked_secret', (string) $body);
        $this->assertStringNotContainsString('hmac_leaked_secret', (string) $body);
    }
}
