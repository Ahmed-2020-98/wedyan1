<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Availability;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Painting;
use App\Models\Payment;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class CheckoutAndOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private ShippingRate $rate;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->customer = User::factory()->customer()->create();

        $zone = ShippingZone::create(['name' => 'السعودية', 'countries' => ['SA']]);
        $this->rate = ShippingRate::create([
            'shipping_zone_id' => $zone->id,
            'name' => 'شحن قياسي',
            'cost' => 35,
            'free_shipping_threshold' => 2000,
        ]);
    }

    /** @return array{0: Order, 1: Payment} */
    private function checkout(Painting $painting): array
    {
        $this->actingAs($this->customer)->postJson('/api/v1/cart/items', ['painting_id' => $painting->id]);

        $response = $this->actingAs($this->customer)->postJson('/api/v1/checkout', [
            'recipient_name' => 'سارة العتيبي',
            'phone' => '0501234567',
            'country' => 'SA',
            'city' => 'الرياض',
            'street' => 'طريق أنس بن مالك',
            'shipping_rate_id' => $this->rate->id,
        ])->assertCreated();

        $order = Order::where('order_number', $response->json('data.order.orderNumber'))->firstOrFail();

        return [$order, $order->payments()->firstOrFail()];
    }

    public function test_checkout_creates_an_order_awaiting_payment(): void
    {
        $painting = Painting::factory()->create(['price' => 1000]);

        [$order] = $this->checkout($painting);

        $this->assertSame(OrderStatus::PaymentPending, $order->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        // 1000 + 35 شحن، ثم 15٪ ضريبة على 1035
        $this->assertEqualsWithDelta(1190.25, (float) $order->grand_total, 0.01);
    }

    public function test_the_free_shipping_threshold_is_applied(): void
    {
        $painting = Painting::factory()->create(['price' => 3000]);

        [$order] = $this->checkout($painting);

        $this->assertEqualsWithDelta(0, (float) $order->shipping_total, 0.01);
    }

    /**
     * المخزون يُحجز عند إنشاء الطلب لا عند الدفع، وتبقى الحالة «محجوزة»
     * لا «مباعة» — لأن البيع لم يكتمل بعد.
     */
    public function test_checkout_reserves_the_painting_before_payment(): void
    {
        $painting = Painting::factory()->create();

        $this->checkout($painting);

        $painting->refresh();
        $this->assertSame(0, $painting->stock_quantity);
        $this->assertSame(Availability::Reserved, $painting->availability);
    }

    public function test_the_cart_is_emptied_after_checkout(): void
    {
        $painting = Painting::factory()->create();

        $this->checkout($painting);

        $this->actingAs($this->customer)
            ->getJson('/api/v1/cart')
            ->assertJsonPath('data.totals.itemCount', 0);
    }

    public function test_checkout_fails_when_the_cart_is_empty(): void
    {
        $this->actingAs($this->customer)->postJson('/api/v1/checkout', [
            'recipient_name' => 'سارة',
            'phone' => '0501234567',
            'country' => 'SA',
            'city' => 'الرياض',
            'street' => 'شارع',
            'shipping_rate_id' => $this->rate->id,
        ])->assertStatus(422);
    }

    /**
     * لا تتقدّم حالة الطلب إلا بخطّاف موثَّق.
     * نقطة الخطّاف عامة على الإنترنت، ومن يعرف عنوانها يستطيع ادّعاء
     * نجاح أي عملية لولا التحقق من التوقيع.
     */
    public function test_a_verified_webhook_marks_the_order_paid_and_the_painting_sold(): void
    {
        $painting = Painting::factory()->create();
        [$order, $payment] = $this->checkout($painting);

        $this->postJson('/payments/webhook/fake', [
            'gateway_payment_id' => $payment->gateway_payment_id,
            'amount' => (float) $payment->amount,
            'success' => true,
        ])->assertOk()->assertJsonPath('verified', true);

        $order->refresh();
        $painting->refresh();

        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(Availability::Sold, $painting->availability);
        $this->assertSame(1, $painting->sold_count);
    }

    /**
     * المزوّدون يعيدون إرسال الخطّاف عند أي شكّ في التسليم.
     * دفعة وصلت مرّتين يجب ألّا تُرحّل الطلب مرّتين ولا تُضاعف المبيعات.
     */
    public function test_replaying_the_same_webhook_changes_nothing(): void
    {
        $painting = Painting::factory()->create();
        [$order, $payment] = $this->checkout($painting);

        $body = [
            'gateway_payment_id' => $payment->gateway_payment_id,
            'amount' => (float) $payment->amount,
            'success' => true,
        ];

        foreach (range(1, 4) as $ignored) {
            $this->postJson('/payments/webhook/fake', $body)->assertOk();
        }

        $painting->refresh();

        $this->assertSame(1, $painting->sold_count);
        $this->assertSame(1, $order->payments()->where('status', PaymentStatus::Paid->value)->count());
        // كل محاولة تُسجَّل ولو لم تُغيّر شيئاً — هذا هو سجلّ التدقيق.
        $this->assertSame(4, $payment->transactions()->count());
    }

    public function test_a_failed_payment_leaves_the_order_unpaid(): void
    {
        $painting = Painting::factory()->create();
        [$order, $payment] = $this->checkout($painting);

        $this->postJson('/payments/webhook/fake', [
            'gateway_payment_id' => $payment->gateway_payment_id,
            'amount' => (float) $payment->amount,
            'success' => false,
        ])->assertOk();

        $order->refresh();

        $this->assertNotSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::PaymentPending, $order->status);
    }

    public function test_a_customer_cannot_read_another_customers_order(): void
    {
        $painting = Painting::factory()->create();
        [$order] = $this->checkout($painting);

        $intruder = User::factory()->customer()->create();

        $this->actingAs($intruder)
            ->getJson("/api/v1/orders/{$order->order_number}")
            ->assertNotFound();
    }

    public function test_cancelling_an_order_returns_the_painting_to_the_gallery(): void
    {
        $painting = Painting::factory()->create();
        [$order] = $this->checkout($painting);

        $this->actingAs($this->customer)
            ->postJson("/api/v1/orders/{$order->order_number}/cancel")
            ->assertOk();

        $painting->refresh();

        $this->assertSame(Availability::Available, $painting->availability);
        $this->assertSame(1, $painting->stock_quantity);
    }

    /** جدول الانتقالات يُطبَّق في الخادم — اللوحة تستطيع إرسال أي قيمة. */
    public function test_an_illegal_status_transition_is_rejected(): void
    {
        $painting = Painting::factory()->create();
        [$order] = $this->checkout($painting);

        $artist = User::factory()->artist()->create();

        // من «بانتظار الدفع» مباشرةً إلى «تم التسليم» — قفزة غير مسموحة
        $this->actingAs($artist)
            ->putJson("/api/v1/manage/orders/{$order->order_number}/status", ['status' => 'delivered'])
            ->assertStatus(422);
    }
}
