<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentKind;
use App\Enums\ReservationStatus;
use App\Models\Painting;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class ReservationTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $artist;
    private Painting $painting;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->customer = User::factory()->customer()->create();
        $this->artist = User::factory()->artist()->create();
        $this->painting = Painting::factory()->reservable()->create([
            'artist_id' => $this->artist->id,
            'price' => 4000,
            'deposit_percentage' => 50,
        ]);
    }

    private function request(): Reservation
    {
        $response = $this->actingAs($this->customer)->postJson('/api/v1/reservations', [
            'painting_id' => $this->painting->id,
            'description' => 'بألوان أدفأ لصالة استقبال',
            'width' => 60,
            'height' => 90,
            'recipient_name' => 'سارة',
            'phone' => '0501234567',
            'city' => 'الرياض',
            'street' => 'طريق أنس بن مالك',
        ])->assertCreated();

        return Reservation::where('reservation_number', $response->json('data.reservationNumber'))->firstOrFail();
    }

    private function payDue(Reservation $reservation): void
    {
        $response = $this->actingAs($this->customer)
            ->postJson("/api/v1/reservations/{$reservation->reservation_number}/pay")
            ->assertCreated();

        $payment = $reservation->payments()
            ->where('payment_number', $response->json('data.paymentNumber'))
            ->firstOrFail();

        $this->postJson('/payments/webhook/fake', [
            'gateway_payment_id' => $payment->gateway_payment_id,
            'amount' => (float) $payment->amount,
            'success' => true,
        ])->assertOk();
    }

    /**
     * السعر يبقى فارغاً عند الطلب.
     * اللوحة المخصّصة تُسعَّر بعد فهم المطلوب لا قبله، ولذلك لا دفع هنا.
     */
    public function test_a_new_reservation_has_no_price_and_cannot_be_paid(): void
    {
        $reservation = $this->request();

        $this->assertSame(ReservationStatus::Pending, $reservation->status);
        $this->assertNull($reservation->price);

        $this->actingAs($this->customer)
            ->postJson("/api/v1/reservations/{$reservation->reservation_number}/pay")
            ->assertStatus(422);
    }

    public function test_a_ready_made_painting_cannot_be_reserved(): void
    {
        $readyMade = Painting::factory()->create();

        $this->actingAs($this->customer)->postJson('/api/v1/reservations', [
            'painting_id' => $readyMade->id,
            'description' => 'أريدها',
            'recipient_name' => 'سارة',
            'phone' => '0501234567',
            'city' => 'الرياض',
            'street' => 'شارع',
        ])->assertStatus(422);
    }

    public function test_the_artist_accepts_and_sets_the_price_and_deposit(): void
    {
        $reservation = $this->request();

        $this->actingAs($this->artist)
            ->postJson("/api/v1/manage/reservations/{$reservation->reservation_number}/accept", [
                'price' => 4800,
                'note' => 'سأبدأ فور وصول العربون.',
            ])
            ->assertOk()
            ->assertJsonPath('data.statusLabel', 'مقبول');

        $reservation->refresh();

        $this->assertEqualsWithDelta(4800, (float) $reservation->price, 0.01);
        $this->assertEqualsWithDelta(2400, (float) $reservation->deposit_amount, 0.01);
        $this->assertEqualsWithDelta(2400, $reservation->amountDueNow(), 0.01);
    }

    /** دفع العربون يبدأ الإنتاج تلقائياً. */
    public function test_paying_the_deposit_starts_production(): void
    {
        $reservation = $this->request();

        $this->actingAs($this->artist)->postJson(
            "/api/v1/manage/reservations/{$reservation->reservation_number}/accept",
            ['price' => 4800],
        );

        $this->payDue($reservation->refresh());
        $reservation->refresh();

        $this->assertSame(ReservationStatus::InProduction, $reservation->status);
        $this->assertEqualsWithDelta(2400, (float) $reservation->paid_amount, 0.01);
        $this->assertEqualsWithDelta(2400, $reservation->remainingAmount(), 0.01);
        $this->assertNotNull($reservation->production_started_at);
        $this->assertSame(PaymentKind::Deposit, $reservation->payments()->first()->kind);
    }

    /** المسار الكامل: طلب ← قبول ← عربون ← مراحل ← اكتمال ← سداد ← تسليم. */
    public function test_the_full_reservation_lifecycle(): void
    {
        $reservation = $this->request();

        $this->actingAs($this->artist)->postJson(
            "/api/v1/manage/reservations/{$reservation->reservation_number}/accept",
            ['price' => 4800],
        )->assertOk();

        $this->payDue($reservation->refresh());

        $this->actingAs($this->artist)->postJson(
            "/api/v1/manage/reservations/{$reservation->reservation_number}/updates",
            ['title' => 'طبقة الألوان الأولى', 'progress_percentage' => 60],
        )->assertCreated();

        $this->actingAs($this->artist)->postJson(
            "/api/v1/manage/reservations/{$reservation->reservation_number}/complete",
        )->assertOk();

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Completed, $reservation->status);
        $this->assertSame(100, $reservation->progress_percentage);

        // الدفعة الثانية هي المتبقّي، وتُجهّز اللوحة للتسليم
        $this->payDue($reservation);
        $reservation->refresh();

        $this->assertSame(ReservationStatus::ReadyForDelivery, $reservation->status);
        $this->assertTrue($reservation->isFullyPaid());
        $this->assertEqualsWithDelta(4800, $reservation->settledAmount(), 0.01);
        $this->assertSame(PaymentKind::Balance, $reservation->payments()->latest('id')->first()->kind);
    }

    public function test_the_artist_can_decline_with_a_reason(): void
    {
        $reservation = $this->request();

        $this->actingAs($this->artist)
            ->postJson("/api/v1/manage/reservations/{$reservation->reservation_number}/reject", [
                'reason' => 'جدولي ممتلئ حتى نهاية العام.',
            ])
            ->assertOk();

        $reservation->refresh();

        $this->assertSame(ReservationStatus::Rejected, $reservation->status);
        $this->assertNotNull($reservation->rejection_reason);
    }

    /**
     * المبلغ يُحسب في الخادم من حالة الحجز ولا يُقرأ من الطلب.
     * لو قُرئ لأمكن للزبون إرسال ريال واحد واعتبار لوحته مدفوعة.
     */
    public function test_the_customer_cannot_dictate_the_amount_to_pay(): void
    {
        $reservation = $this->request();

        $this->actingAs($this->artist)->postJson(
            "/api/v1/manage/reservations/{$reservation->reservation_number}/accept",
            ['price' => 4800],
        );

        $response = $this->actingAs($this->customer)->postJson(
            "/api/v1/reservations/{$reservation->reservation_number}/pay",
            ['amount' => 1, 'price' => 1],
        )->assertCreated();

        $payment = $reservation->payments()
            ->where('payment_number', $response->json('data.paymentNumber'))
            ->firstOrFail();

        $this->assertEqualsWithDelta(2400, (float) $payment->amount, 0.01);
    }

    public function test_a_customer_cannot_read_another_customers_reservation(): void
    {
        $reservation = $this->request();
        $intruder = User::factory()->customer()->create();

        $this->actingAs($intruder)
            ->getJson("/api/v1/reservations/{$reservation->reservation_number}")
            ->assertNotFound();
    }

    public function test_a_customer_cannot_accept_their_own_reservation(): void
    {
        $reservation = $this->request();

        $this->actingAs($this->customer)
            ->postJson("/api/v1/manage/reservations/{$reservation->reservation_number}/accept", ['price' => 10])
            ->assertForbidden();
    }

    public function test_internal_updates_are_hidden_from_the_customer(): void
    {
        $reservation = $this->request();

        $this->actingAs($this->artist)->postJson(
            "/api/v1/manage/reservations/{$reservation->reservation_number}/updates",
            ['title' => 'ملاحظة داخلية', 'visible_to_customer' => false],
        )->assertCreated();

        $response = $this->actingAs($this->customer)
            ->getJson("/api/v1/reservations/{$reservation->reservation_number}")
            ->assertOk();

        $titles = array_column($response->json('data.updates'), 'title');
        $this->assertNotContains('ملاحظة داخلية', $titles);
    }
}
