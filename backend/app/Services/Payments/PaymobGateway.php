<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\DataObjects\PaymentIntent;
use App\DataObjects\WebhookResult;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * بوابة Paymob — السعودية ومصر.
 *
 * تعتمد واجهة النوايا (Intention API) مع صفحة الدفع الموحّدة المستضافة:
 * الزبون يُدخل بيانات بطاقته على نطاق Paymob لا على نطاقنا، فلا تمرّ
 * البطاقة بخوادمنا ولا نقع تحت التزامات PCI-DSS الكاملة.
 *
 * المراجع: https://developers.paymob.com
 */
final class PaymobGateway implements PaymentGateway
{
    /**
     * ترتيب الحقول في حساب التوقيع مُلزم ولا يجوز تغييره.
     *
     * Paymob تسلسل هذه القيم بهذا الترتيب بالضبط ثم توقّعها بـ HMAC-SHA512.
     * أي إعادة ترتيب — ولو بدت أنظف — تُنتج توقيعاً مختلفاً فتُرفض كل
     * الخطّافات الصحيحة.
     *
     * @var list<string>
     */
    private const HMAC_FIELDS = [
        'amount_cents',
        'created_at',
        'currency',
        'error_occured',
        'has_parent_transaction',
        'id',
        'integration_id',
        'is_3d_secure',
        'is_auth',
        'is_capture',
        'is_refunded',
        'is_standalone_payment',
        'is_voided',
        'order.id',
        'owner',
        'source_data.pan',
        'source_data.sub_type',
        'source_data.type',
        'success',
    ];

    public function name(): string
    {
        return 'paymob';
    }

    public function createPayment(Payment $payment, array $context): PaymentIntent
    {
        $payable = $payment->payable;
        $user = $payment->user;

        [$firstName, $lastName] = $this->splitName((string) $user->name);

        $response = $this->client()->post('/v1/intention/', [
            // بالوحدة الصغرى (هللة/قرش) — إرسال الريال مباشرةً يُحصّل
            // واحداً بالمئة من المبلغ المقصود.
            'amount' => (int) round((float) $payment->amount * 100),
            'currency' => $payment->currency,
            'payment_methods' => $this->integrationIds(),
            'special_reference' => $payment->payment_number,
            'notification_url' => route('payments.webhook', ['gateway' => 'paymob']),
            'redirection_url' => $context['returnUrl'],
            'items' => [[
                'name' => mb_substr($context['description'], 0, 50),
                'amount' => (int) round((float) $payment->amount * 100),
                'quantity' => 1,
            ]],
            'billing_data' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $user->email,
                'phone_number' => $user->phone ?? 'NA',
                'country' => $payable->shipping_country ?? 'SA',
                'city' => $payable->shipping_city ?? 'NA',
                'street' => $payable->shipping_street ?? 'NA',
                'building' => 'NA',
                'floor' => 'NA',
                'apartment' => 'NA',
                'state' => 'NA',
            ],
        ]);

        if ($response->failed()) {
            Log::error('paymob.intention.failed', [
                'payment' => $payment->payment_number,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new RuntimeException('تعذّر إنشاء عملية الدفع لدى المزوّد.');
        }

        $clientSecret = (string) $response->json('client_secret');

        return new PaymentIntent(
            redirectUrl: sprintf(
                '%s/unifiedcheckout/?publicKey=%s&clientSecret=%s',
                $this->baseUrl(),
                (string) config('services.paymob.public_key'),
                $clientSecret,
            ),
            gatewayPaymentId: (string) ($response->json('id') ?? $payment->payment_number),
            meta: ['client_secret' => $clientSecret],
        );
    }

    public function verify(Payment $payment): WebhookResult
    {
        $response = $this->client()
            ->get('/api/acceptance/transactions/' . $payment->gateway_payment_id);

        $data = $response->json() ?? [];
        $success = (bool) ($data['success'] ?? false);

        return new WebhookResult(
            // استجابة مباشرة من المزوّد بمفتاحنا السرّي — موثوقة كالتوقيع.
            signatureVerified: $response->successful(),
            gatewayPaymentId: (string) ($data['id'] ?? $payment->gateway_payment_id),
            status: $success ? PaymentStatus::Paid : PaymentStatus::Failed,
            amount: isset($data['amount_cents']) ? ((int) $data['amount_cents']) / 100 : null,
            event: 'verify',
            payload: $data,
        );
    }

    public function parseWebhook(Request $request): WebhookResult
    {
        $payload = $request->all();
        $transaction = $payload['obj'] ?? [];

        $verified = $this->verifySignature(
            $transaction,
            (string) ($request->query('hmac') ?? $payload['hmac'] ?? ''),
        );

        $success = (bool) ($transaction['success'] ?? false);
        $pending = (bool) ($transaction['pending'] ?? false);

        $status = match (true) {
            $success && ! $pending => PaymentStatus::Paid,
            $pending => PaymentStatus::Processing,
            default => PaymentStatus::Failed,
        };

        return new WebhookResult(
            signatureVerified: $verified,
            gatewayPaymentId: isset($transaction['id']) ? (string) $transaction['id'] : null,
            status: $status,
            amount: isset($transaction['amount_cents']) ? ((int) $transaction['amount_cents']) / 100 : null,
            event: (string) ($payload['type'] ?? 'TRANSACTION'),
            payload: $payload,
            gatewayReference: $transaction['order']['merchant_order_id'] ?? null,
            failureReason: $success ? null : ($transaction['data']['message'] ?? null),
        );
    }

    public function refund(Payment $payment, float $amount): bool
    {
        $response = $this->client()->post('/api/acceptance/void_refund/refund', [
            'transaction_id' => $payment->gateway_payment_id,
            'amount_cents' => (int) round($amount * 100),
        ]);

        if ($response->failed()) {
            Log::error('paymob.refund.failed', [
                'payment' => $payment->payment_number,
                'body' => $response->json(),
            ]);
        }

        return $response->successful();
    }

    /**
     * يحسب HMAC-SHA512 على القيم المسلسلة ويقارنه بالوارد.
     *
     * hash_equals لا '===': المقارنة النصّية العادية تخرج عند أول بايت
     * مختلف، وفارق التوقيت يسمح نظرياً باستنتاج التوقيع الصحيح بايتاً بايتاً.
     */
    public function verifySignature(array $transaction, string $received): bool
    {
        $secret = (string) config('services.paymob.hmac_secret');

        if ($secret === '' || $received === '') {
            return false;
        }

        $concatenated = '';

        foreach (self::HMAC_FIELDS as $field) {
            $concatenated .= $this->stringify(data_get($transaction, $field));
        }

        return hash_equals(hash_hmac('sha512', $concatenated, $secret), $received);
    }

    /**
     * القيم المنطقية تُسلسل كـ "true"/"false" لا "1"/"" — هذا ما ترسله
     * Paymob في JSON وما تحسب عليه توقيعها.
     */
    private function stringify(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => '',
            default => (string) $value,
        };
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withToken((string) config('services.paymob.secret_key'), 'Token')
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 200);
    }

    private function baseUrl(): string
    {
        $region = (string) config('services.paymob.region', 'ksa');

        return (string) (config("services.paymob.base_urls.{$region}")
            ?? config('services.paymob.base_urls.ksa'));
    }

    /** @return list<array{integration_id: int}> */
    private function integrationIds(): array
    {
        return array_map(
            fn (int $id): array => ['integration_id' => $id],
            (array) config('services.paymob.integration_ids', []),
        );
    }

    /** @return array{0: string, 1: string} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];

        // Paymob تشترط الاسمين، وترفض الحقل الفارغ.
        return [$parts[0] ?? 'NA', count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'NA'];
    }
}
