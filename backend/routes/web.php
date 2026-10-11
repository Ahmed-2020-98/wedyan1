<?php

declare(strict_types=1);

use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مسارات الويب
|--------------------------------------------------------------------------
|
| المنصة واجهة برمجية تخدم تطبيقي Angular، فلا صفحات هنا عدا ما يحتاج
| متصفحاً فعلياً: خطّافات الدفع وصفحة المحاكاة.
|
*/

Route::get('/', fn () => redirect()->away((string) config('platform.urls.web')));

// خريطة الموقع مولّدة من قاعدة البيانات ومُخزَّنة مؤقتاً لساعة.
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

/*
 | الخطّاف بلا CSRF — الطلب يأتي من خادم المزوّد لا من متصفح فيه جلسة.
 | التحقق هنا بالتوقيع لا بالرمز، انظر PaymobGateway::verifySignature
 */
Route::match(['get', 'post'], '/payments/webhook/{gateway}', [PaymentWebhookController::class, 'handle'])
    ->name('payments.webhook');

/*
 | صفحة المحاكاة مسجّلة فقط حين يكون السائق fake.
 | التسجيل المشروط أقوى من فحص داخل الدالة: في الإنتاج لا وجود للمسار
 | أصلاً، فلا يمكن بلوغه ولو بخطأ في الإعداد.
 */
if (config('services.payment.driver') === 'fake') {
    Route::get('/payments/simulator/{payment}', [PaymentWebhookController::class, 'simulator'])
        ->name('payments.simulator');

    Route::post('/payments/simulator/{payment}', [PaymentWebhookController::class, 'simulate'])
        ->name('payments.simulate');
}
