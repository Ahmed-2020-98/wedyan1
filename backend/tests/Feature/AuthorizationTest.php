<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Painting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * حدود الأدوار.
 *
 * هذه الاختبارات هي ما يمنع انزلاق صلاحية عند إضافة نقطة جديدة لاحقاً.
 */
final class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public static function manageEndpoints(): array
    {
        return [
            'dashboard' => ['GET', '/api/v1/manage/dashboard'],
            'paintings' => ['GET', '/api/v1/manage/paintings'],
            'orders' => ['GET', '/api/v1/manage/orders'],
            'reservations' => ['GET', '/api/v1/manage/reservations'],
            'reviews' => ['GET', '/api/v1/manage/reviews'],
        ];
    }

    public static function adminEndpoints(): array
    {
        return [
            'users' => ['GET', '/api/v1/admin/users'],
            'coupons' => ['GET', '/api/v1/admin/coupons'],
            'payments' => ['GET', '/api/v1/admin/payments'],
            'shipping' => ['GET', '/api/v1/admin/shipping'],
            'settings' => ['GET', '/api/v1/admin/settings'],
        ];
    }

    #[DataProvider('manageEndpoints')]
    public function test_a_customer_is_denied_the_dashboard(string $method, string $uri): void
    {
        $this->actingAs(User::factory()->customer()->create())
            ->json($method, $uri)
            ->assertForbidden();
    }

    #[DataProvider('manageEndpoints')]
    public function test_an_artist_is_allowed_the_dashboard(string $method, string $uri): void
    {
        $this->actingAs(User::factory()->artist()->create())
            ->json($method, $uri)
            ->assertOk();
    }

    #[DataProvider('adminEndpoints')]
    public function test_an_artist_is_denied_admin_only_sections(string $method, string $uri): void
    {
        $this->actingAs(User::factory()->artist()->create())
            ->json($method, $uri)
            ->assertForbidden();
    }

    #[DataProvider('adminEndpoints')]
    public function test_an_admin_is_allowed_admin_only_sections(string $method, string $uri): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->json($method, $uri)
            ->assertOk();
    }

    public function test_a_customer_cannot_create_a_painting(): void
    {
        $this->actingAs(User::factory()->customer()->create())
            ->postJson('/api/v1/manage/paintings', [
                'title' => 'محاولة',
                'price' => 100,
                'painting_type' => 'ready_made',
            ])
            ->assertForbidden();
    }

    public function test_an_artist_can_create_and_delete_a_painting(): void
    {
        $artist = User::factory()->artist()->create();

        $response = $this->actingAs($artist)
            ->postJson('/api/v1/manage/paintings', [
                'title' => 'عمل جديد',
                'price' => 2500,
                'painting_type' => 'ready_made',
            ])
            ->assertCreated();

        $id = $response->json('data.id');

        $this->assertDatabaseHas('paintings', ['id' => $id, 'artist_id' => $artist->id]);

        $this->actingAs($artist)->deleteJson("/api/v1/manage/paintings/{$id}")->assertOk();
        $this->assertSoftDeleted('paintings', ['id' => $id]);
    }

    /**
     * الفنان لا يختار مالك اللوحة.
     * تمرير artist_id في الطلب يجب أن يُتجاهل تماماً.
     */
    public function test_an_artist_cannot_assign_a_painting_to_someone_else(): void
    {
        $artist = User::factory()->artist()->create();
        $other = User::factory()->artist()->create();

        $response = $this->actingAs($artist)
            ->postJson('/api/v1/manage/paintings', [
                'title' => 'عمل',
                'price' => 100,
                'painting_type' => 'ready_made',
                'artist_id' => $other->id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('paintings', [
            'id' => $response->json('data.id'),
            'artist_id' => $artist->id,
        ]);
    }

    public function test_an_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/users/{$admin->id}/toggle-active")
            ->assertStatus(422);
    }

    public function test_public_catalog_endpoints_need_no_authentication(): void
    {
        Painting::factory()->create();

        $this->getJson('/api/v1/paintings')->assertOk();
        $this->getJson('/api/v1/categories')->assertOk();
        $this->getJson('/api/v1/home')->assertOk();
        $this->getJson('/api/v1/meta')->assertOk();
    }
}
