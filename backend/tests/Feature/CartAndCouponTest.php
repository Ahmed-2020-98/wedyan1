<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\Painting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CartAndCouponTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->customer()->create();
    }

    public function test_a_customer_can_add_a_painting_to_the_cart(): void
    {
        $painting = Painting::factory()->create(['price' => 2000]);

        $this->actingAs($this->customer)
            ->postJson('/api/v1/cart/items', ['painting_id' => $painting->id])
            ->assertOk()
            ->assertJsonPath('data.totals.itemCount', 1)
            ->assertJsonPath('data.totals.subtotal.amount', 2000);
    }

    public function test_a_sold_painting_cannot_be_added(): void
    {
        $painting = Painting::factory()->sold()->create();

        $this->actingAs($this->customer)
            ->postJson('/api/v1/cart/items', ['painting_id' => $painting->id])
            ->assertStatus(422);
    }

    public function test_a_reservable_painting_cannot_be_added_to_the_cart(): void
    {
        $painting = Painting::factory()->reservable()->create();

        $this->actingAs($this->customer)
            ->postJson('/api/v1/cart/items', ['painting_id' => $painting->id])
            ->assertStatus(422);
    }

    /**
     * الضريبة تُحتسب بعد الخصم لا قبله.
     * حسابها على المبلغ الكامل يحمّل الزبون ضريبة مبلغ لم يدفعه.
     */
    public function test_vat_is_calculated_after_the_discount(): void
    {
        $painting = Painting::factory()->create(['price' => 1000]);

        Coupon::create([
            'code' => 'TEN',
            'type' => CouponType::Percentage,
            'value' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($this->customer)->postJson('/api/v1/cart/items', ['painting_id' => $painting->id]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/cart/coupon', ['code' => 'TEN'])
            ->assertOk();

        $totals = $response->json('data.totals');

        $this->assertEqualsWithDelta(1000, $totals['subtotal']['amount'], 0.001);
        $this->assertEqualsWithDelta(100, $totals['discount']['amount'], 0.001);
        // (1000 − 100) × 15٪ = 135، لا 150
        $this->assertEqualsWithDelta(135, $totals['tax']['amount'], 0.001);
        $this->assertEqualsWithDelta(1035, $totals['total']['amount'], 0.001);
    }

    /** سقف الخصم يحمي من نسبة مئوية على لوحة باهظة. */
    public function test_the_discount_is_capped_by_the_maximum(): void
    {
        $painting = Painting::factory()->create(['price' => 10000]);

        Coupon::create([
            'code' => 'BIG',
            'type' => CouponType::Percentage,
            'value' => 50,
            'max_discount_amount' => 500,
            'is_active' => true,
        ]);

        $this->actingAs($this->customer)->postJson('/api/v1/cart/items', ['painting_id' => $painting->id]);

        $this->actingAs($this->customer)
            ->postJson('/api/v1/cart/coupon', ['code' => 'BIG'])
            ->assertOk()
            ->assertJsonPath('data.totals.discount.amount', 500);
    }

    public function test_an_expired_coupon_is_rejected(): void
    {
        $painting = Painting::factory()->create();

        Coupon::create([
            'code' => 'OLD',
            'type' => CouponType::Percentage,
            'value' => 20,
            'expires_at' => now()->subDay(),
            'is_active' => true,
        ]);

        $this->actingAs($this->customer)->postJson('/api/v1/cart/items', ['painting_id' => $painting->id]);

        $this->actingAs($this->customer)
            ->postJson('/api/v1/cart/coupon', ['code' => 'OLD'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('coupon');
    }

    public function test_a_coupon_below_its_minimum_order_is_rejected(): void
    {
        $painting = Painting::factory()->create(['price' => 100]);

        Coupon::create([
            'code' => 'MIN',
            'type' => CouponType::Fixed,
            'value' => 50,
            'min_order_amount' => 1000,
            'is_active' => true,
        ]);

        $this->actingAs($this->customer)->postJson('/api/v1/cart/items', ['painting_id' => $painting->id]);

        $this->actingAs($this->customer)
            ->postJson('/api/v1/cart/coupon', ['code' => 'MIN'])
            ->assertStatus(422);
    }

    /** خصم أكبر من قيمة الطلب ينتج إجمالياً سالباً يرفضه المزوّد. */
    public function test_a_fixed_discount_never_exceeds_the_subtotal(): void
    {
        $painting = Painting::factory()->create(['price' => 100]);

        Coupon::create([
            'code' => 'HUGE',
            'type' => CouponType::Fixed,
            'value' => 5000,
            'is_active' => true,
        ]);

        $this->actingAs($this->customer)->postJson('/api/v1/cart/items', ['painting_id' => $painting->id]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/v1/cart/coupon', ['code' => 'HUGE'])
            ->assertOk();

        $this->assertEqualsWithDelta(100, $response->json('data.totals.discount.amount'), 0.001);
        $this->assertGreaterThanOrEqual(0, $response->json('data.totals.total.amount'));
    }

    /**
     * اللوحة قطعة واحدة وقد تُباع بينما هي في سلة زبون آخر.
     * التنظيف عند عرض السلة يجعله يكتشف ذلك قبل إدخال بطاقته.
     */
    public function test_the_cart_drops_paintings_that_are_no_longer_available(): void
    {
        $painting = Painting::factory()->create();

        $this->actingAs($this->customer)->postJson('/api/v1/cart/items', ['painting_id' => $painting->id]);

        $painting->update(['availability' => 'sold', 'stock_quantity' => 0]);

        $response = $this->actingAs($this->customer)->getJson('/api/v1/cart')->assertOk();

        $this->assertSame(0, $response->json('data.totals.itemCount'));
        $this->assertContains($painting->title, $response->json('removed'));
    }

    public function test_a_customer_cannot_touch_another_customers_cart_item(): void
    {
        $painting = Painting::factory()->create();
        $intruder = User::factory()->customer()->create();

        $this->actingAs($this->customer)->postJson('/api/v1/cart/items', ['painting_id' => $painting->id]);
        $itemId = $this->actingAs($this->customer)->getJson('/api/v1/cart')->json('data.items.0.id');

        $this->actingAs($intruder)
            ->deleteJson("/api/v1/cart/items/{$itemId}")
            ->assertForbidden();
    }
}
