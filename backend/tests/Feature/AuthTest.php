<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_register_and_receives_a_token(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'نورة الشمري',
            'email' => 'noura@example.com',
            'phone' => '0551234567',
            'password' => 'Passw0rd!',
            'password_confirmation' => 'Passw0rd!',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'noura@example.com')
            ->assertJsonPath('data.user.isActive', true)
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertDatabaseHas('users', ['email' => 'noura@example.com']);
    }

    /**
     * حارس تصعيد الصلاحيات.
     *
     * لو قُرئ الدور من الطلب لأمكن لأي زائر أن يسجّل نفسه مديراً. هذا
     * الاختبار هو ما يمنع عودة تلك الثغرة لاحقاً.
     */
    public function test_registration_ignores_any_role_sent_by_the_client(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'مهاجم',
            'email' => 'attacker@example.com',
            'phone' => '0559999999',
            'password' => 'Passw0rd!',
            'password_confirmation' => 'Passw0rd!',
            'role' => 'admin',
            'roles' => ['admin'],
        ])->assertCreated();

        $this->assertSame(['customer'], $response->json('data.user.roles'));
        $this->assertFalse(User::where('email', 'attacker@example.com')->first()->isAdmin());
    }

    public function test_registration_rejects_a_non_saudi_mobile_number(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'x',
            'email' => 'x@example.com',
            'phone' => '0111234567',
            'password' => 'Passw0rd!',
            'password_confirmation' => 'Passw0rd!',
        ])->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_registration_rejects_a_weak_password(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'x',
            'email' => 'x@example.com',
            'phone' => '0551112222',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_a_customer_can_log_in(): void
    {
        $user = User::factory()->customer()->create(['email' => 'sara@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'sara@example.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['token']]);
    }

    /**
     * الرسالة واحدة في الحالتين.
     *
     * لو اختلفت لأصبحت نقطة الدخول عدّاداً مجانياً للحسابات المسجّلة.
     */
    public function test_login_does_not_reveal_whether_an_email_exists(): void
    {
        User::factory()->customer()->create(['email' => 'known@example.com']);

        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            'email' => 'known@example.com',
            'password' => 'incorrect',
        ])->assertStatus(422);

        $unknownEmail = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'incorrect',
        ])->assertStatus(422);

        $this->assertSame($wrongPassword->json('message'), $unknownEmail->json('message'));
    }

    public function test_a_deactivated_account_cannot_log_in(): void
    {
        User::factory()->customer()->create([
            'email' => 'blocked@example.com',
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'blocked@example.com',
            'password' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    /**
     * التعطيل يجب أن يسري فوراً على الرموز القائمة، لا عند انتهائها.
     * وإلّا صار زرّ «تعطيل الحساب» بلا مفعول في اللحظة التي يُحتاج فيها.
     */
    public function test_deactivating_an_account_invalidates_its_existing_token(): void
    {
        $user = User::factory()->customer()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();

        $user->update(['is_active' => false]);
        $this->forgetAuthenticatedUser();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
    }

    public function test_protected_endpoints_reject_anonymous_requests(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->getJson('/api/v1/cart')->assertUnauthorized();
        $this->getJson('/api/v1/orders')->assertUnauthorized();
    }

    public function test_logging_out_revokes_only_the_current_token(): void
    {
        $user = User::factory()->customer()->create();
        $tokenA = $user->createToken('a')->plainTextToken;
        $tokenB = $user->createToken('b')->plainTextToken;

        $this->withToken($tokenA)->postJson('/api/v1/auth/logout')->assertOk();
        $this->forgetAuthenticatedUser();

        $this->withToken($tokenA)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->forgetAuthenticatedUser();

        $this->withToken($tokenB)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_changing_the_password_revokes_all_other_sessions(): void
    {
        $user = User::factory()->customer()->create();
        $old = $user->createToken('old')->plainTextToken;

        $response = $this->withToken($old)->putJson('/api/v1/auth/password', [
            'current_password' => 'password',
            'password' => 'NewPassw0rd!',
            'password_confirmation' => 'NewPassw0rd!',
        ])->assertOk();

        $this->forgetAuthenticatedUser();
        $this->withToken($old)->getJson('/api/v1/auth/me')->assertUnauthorized();

        $this->forgetAuthenticatedUser();
        $this->withToken($response->json('data.token'))->getJson('/api/v1/auth/me')->assertOk();
    }
}
