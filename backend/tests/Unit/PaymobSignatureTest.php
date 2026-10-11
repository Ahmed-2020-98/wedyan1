<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Payments\PaymobGateway;
use Tests\TestCase;

/**
 * التحقق من توقيع Paymob.
 *
 * لا قاعدة بيانات ولا شبكة — الغرض تثبيت خوارزمية التوقيع نفسها، فهي
 * الشيفرة التي تقرّر إن كان الطلب الوارد من المزوّد فعلاً أم من مهاجم
 * يعرف عنوان الخطّاف. (نرث TestCase الخاص بلارافيل لأن الصنف يقرأ config.)
 */
final class PaymobSignatureTest extends TestCase
{
    private const SECRET = 'test-hmac-secret';

    /** حمولة معاملة نموذجية بالحقول التي توقّعها Paymob. */
    private function transaction(): array
    {
        return [
            'amount_cents' => 119025,
            'created_at' => '2026-08-19T18:00:00.000000',
            'currency' => 'SAR',
            'error_occured' => false,
            'has_parent_transaction' => false,
            'id' => 123456789,
            'integration_id' => 4242,
            'is_3d_secure' => true,
            'is_auth' => false,
            'is_capture' => false,
            'is_refunded' => false,
            'is_standalone_payment' => true,
            'is_voided' => false,
            'order' => ['id' => 987654321],
            'owner' => 55555,
            'pending' => false,
            'source_data' => ['pan' => '2346', 'sub_type' => 'MasterCard', 'type' => 'card'],
            'success' => true,
        ];
    }

    /**
     * الترتيب مُلزم. نبنيه هنا يدوياً بنفس تسلسل Paymob الموثّق، فلو
     * أُعيد ترتيب الحقول في الصنف فشل هذا الاختبار.
     */
    private function expectedSignature(array $transaction): string
    {
        $order = [
            'amount_cents', 'created_at', 'currency', 'error_occured',
            'has_parent_transaction', 'id', 'integration_id', 'is_3d_secure',
            'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment',
            'is_voided', 'order.id', 'owner', 'source_data.pan',
            'source_data.sub_type', 'source_data.type', 'success',
        ];

        $concatenated = '';

        foreach ($order as $field) {
            $value = data_get($transaction, $field);

            $concatenated .= match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                $value === null => '',
                default => (string) $value,
            };
        }

        return hash_hmac('sha512', $concatenated, self::SECRET);
    }

    private function gateway(): PaymobGateway
    {
        config()->set('services.paymob.hmac_secret', self::SECRET);

        return new PaymobGateway;
    }

    public function test_a_correct_signature_is_accepted(): void
    {
        $transaction = $this->transaction();

        $this->assertTrue(
            $this->gateway()->verifySignature($transaction, $this->expectedSignature($transaction)),
        );
    }

    public function test_a_tampered_amount_invalidates_the_signature(): void
    {
        $transaction = $this->transaction();
        $signature = $this->expectedSignature($transaction);

        // مهاجم يرفع المبلغ بعد التوقيع
        $transaction['amount_cents'] = 1;

        $this->assertFalse($this->gateway()->verifySignature($transaction, $signature));
    }

    public function test_flipping_success_invalidates_the_signature(): void
    {
        $transaction = $this->transaction();
        $signature = $this->expectedSignature($transaction);

        $transaction['success'] = false;

        $this->assertFalse($this->gateway()->verifySignature($transaction, $signature));
    }

    public function test_an_empty_or_missing_signature_is_rejected(): void
    {
        $transaction = $this->transaction();

        $this->assertFalse($this->gateway()->verifySignature($transaction, ''));
        $this->assertFalse($this->gateway()->verifySignature($transaction, 'not-a-signature'));
    }

    /** بلا سرّ مضبوط لا يُقبل أي توقيع — الفشل مغلق لا مفتوح. */
    public function test_verification_fails_closed_when_no_secret_is_configured(): void
    {
        $transaction = $this->transaction();
        $signature = $this->expectedSignature($transaction);

        config()->set('services.paymob.hmac_secret', '');

        $this->assertFalse((new PaymobGateway)->verifySignature($transaction, $signature));
    }

    /** القيم المنطقية تُسلسل "true"/"false" لا "1"/"" — وهو ما ترسله Paymob. */
    public function test_booleans_serialise_as_words_not_digits(): void
    {
        $transaction = $this->transaction();
        $signature = $this->expectedSignature($transaction);

        $this->assertTrue($this->gateway()->verifySignature($transaction, $signature));

        $digitStyle = hash_hmac(
            'sha512',
            '1190252026-08-19T18:00:00.000000SAR' . '' . '' . '1234567894242' . '1' . '' . '' . '' . '1' . '',
            self::SECRET,
        );

        $this->assertFalse($this->gateway()->verifySignature($transaction, $digitStyle));
    }
}
