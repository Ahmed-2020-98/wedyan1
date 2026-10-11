<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * نقاط عامة تصف حالة الخادم وإعداداته المرئية للواجهة.
 */
final class MetaController extends Controller
{
    /**
     * فحص الجاهزية. تستدعيه أدوات النشر قبل تحويل الحركة إلى نسخة جديدة،
     * ولذلك يتحقق من قاعدة البيانات فعلياً بدل أن يعيد 200 دائماً.
     */
    public function health(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            $database = 'ok';
        } catch (\Throwable) {
            // لا نُسرّب نص الخطأ — قد يحوي اسم المضيف أو المستخدم.
            $database = 'unavailable';
        }

        return response()->json([
            'status' => $database === 'ok' ? 'ok' : 'degraded',
            'database' => $database,
            'time' => now()->toIso8601String(),
        ], $database === 'ok' ? 200 : 503);
    }

    /**
     * إعدادات تحتاجها الواجهة قبل أول طلب: العملة والضريبة ومزوّد الدفع.
     * لا تحوي أي مفتاح سرّي — المفاتيح العامة فقط.
     */
    public function meta(): JsonResponse
    {
        return response()->json([
            'data' => [
                'appName' => config('app.name'),
                'locale' => config('app.locale'),
                'currency' => config('services.payment.currency'),
                'tax' => [
                    'enabled' => config('platform.tax.enabled'),
                    'percentage' => config('platform.tax.percentage'),
                ],
                'payment' => [
                    'driver' => config('services.payment.driver'),
                ],
            ],
        ]);
    }

    /**
     * الاشتراك في النشرة.
     *
     * الرد واحد سواء كان البريد مشتركاً من قبل أم لا — وإلا صارت النقطة
     * وسيلة للتحقق من وجود بريد بعينه في قوائمنا.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        NewsletterSubscriber::firstOrCreate(
            ['email' => mb_strtolower($data['email'])],
            ['unsubscribed_at' => null],
        );

        return response()->json(['message' => 'شكراً لك — سنوافيك بكل جديد.']);
    }
}
