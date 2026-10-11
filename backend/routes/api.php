<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaintingController;
use App\Http\Controllers\Api\V1\ReservationController;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\WishlistController;
use App\Http\Controllers\Api\V1\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Api\V1\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\V1\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Api\V1\Admin\ShippingController as AdminShippingController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\Manage\CategoryController as ManageCategoryController;
use App\Http\Controllers\Api\V1\Manage\ReviewController as ManageReviewController;
use App\Http\Controllers\Api\V1\Manage\DashboardController as ManageDashboardController;
use App\Http\Controllers\Api\V1\Manage\OrderController as ManageOrderController;
use App\Http\Controllers\Api\V1\Manage\PaintingController as ManagePaintingController;
use App\Http\Controllers\Api\V1\Manage\ReservationController as ManageReservationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مسارات الواجهة البرمجية
|--------------------------------------------------------------------------
|
| كل شيء تحت /api/v1. الإصدار في المسار لا في الترويسة، لأن الواجهتين
| Angular تُبنيان مرة واحدة ويُضمَّن الرابط في الحزمة، فرؤية الإصدار في
| العنوان تختصر تشخيص الأعطال.
|
| اصطلاح الحماية:
|   - بلا حارس            → عام لأي زائر
|   - auth:sanctum,active → مستخدم مسجّل وحسابه مفعَّل
|   - role:...            → دور بعينه فوق ما سبق
|
*/

Route::prefix('v1')->group(function (): void {

    Route::get('/health', [MetaController::class, 'health']);
    Route::get('/meta', [MetaController::class, 'meta']);
    Route::post('/newsletter', [MetaController::class, 'subscribe'])->middleware('throttle:5,1');

    /*
    |--------------------------------------------------------------------------
    | الكتالوج العام
    |--------------------------------------------------------------------------
    | مفتوح بلا مصادقة: هذه هي الواجهة التي تفهرسها محرّكات البحث.
    */
    Route::get('/home', HomeController::class);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{slug}', [CategoryController::class, 'show']);
    Route::get('/paintings', [PaintingController::class, 'index']);
    Route::get('/paintings/filters', [PaintingController::class, 'filters']);
    Route::get('/paintings/{slug}', [PaintingController::class, 'show']);
    Route::get('/paintings/{slug}/related', [PaintingController::class, 'related']);
    Route::get('/paintings/{slug}/reviews', [ReviewController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | المصادقة
    |--------------------------------------------------------------------------
    | حدّ الطلبات ضيّق هنا تحديداً: هذه النقاط هي هدف تخمين كلمات المرور،
    | وحدّ الواجهة العام (60/دقيقة) واسع جداً لها.
    */
    Route::prefix('auth')->group(function (): void {

        Route::middleware('throttle:10,1')->group(function (): void {
            Route::post('/register', [AuthController::class, 'register']);
            Route::post('/login', [AuthController::class, 'login']);
            Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']);
            Route::post('/reset-password', [PasswordResetController::class, 'reset']);
        });

        // نقطة الهبوط من رسالة البريد — موقّعة، ولذلك لا تحتاج رمز دخول.
        Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:10,1'])
            ->name('verification.verify');

        Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::put('/profile', [AuthController::class, 'updateProfile']);
            Route::put('/password', [AuthController::class, 'changePassword']);
            Route::post('/resend-verification', [EmailVerificationController::class, 'resend'])
                ->middleware('throttle:6,1');
        });
    });


    /*
    |--------------------------------------------------------------------------
    | الزبون
    |--------------------------------------------------------------------------
    | كل ما تحت هذه المجموعة يخصّ صاحب الحساب وحده. لاحظ أن كل نقطة تبحث
    | ضمن علاقات المستخدم لا في الجدول كاملاً، فمعرفة رقم طلب غيرك لا تكفي
    | لرؤيته.
    */
    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {

        // السلة
        Route::get('/cart', [CartController::class, 'show']);
        Route::post('/cart/items', [CartController::class, 'add']);
        Route::put('/cart/items/{item}', [CartController::class, 'updateItem']);
        Route::delete('/cart/items/{item}', [CartController::class, 'removeItem']);
        Route::post('/cart/coupon', [CartController::class, 'applyCoupon']);
        Route::delete('/cart/coupon', [CartController::class, 'removeCoupon']);
        Route::delete('/cart', [CartController::class, 'clear']);

        // المفضّلة
        Route::get('/wishlist', [WishlistController::class, 'index']);
        Route::post('/wishlist', [WishlistController::class, 'store']);
        Route::delete('/wishlist/{painting}', [WishlistController::class, 'destroy']);
        Route::post('/wishlist/{painting}/move-to-cart', [WishlistController::class, 'moveToCart']);

        // العناوين
        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::put('/addresses/{address}', [AddressController::class, 'update']);
        Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);

        // إتمام الطلب
        Route::get('/checkout/shipping-options', [CheckoutController::class, 'shippingOptions']);
        Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:20,1');

        // الطلبات
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{orderNumber}', [OrderController::class, 'show']);
        Route::post('/orders/{orderNumber}/cancel', [OrderController::class, 'cancel']);

        // الحساب والإشعارات والمراجعات
        Route::get('/account/overview', [AccountController::class, 'overview']);
        Route::get('/account/payments', [AccountController::class, 'payments']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::put('/notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::put('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::get('/reviews/mine', [ReviewController::class, 'mine']);
        Route::post('/reviews', [ReviewController::class, 'store']);
        Route::put('/reviews/{review}', [ReviewController::class, 'update']);
        Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);

        // الحجوزات
        Route::get('/reservations', [ReservationController::class, 'index']);
        Route::post('/reservations', [ReservationController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/reservations/{number}', [ReservationController::class, 'show']);
        Route::post('/reservations/{number}/pay', [ReservationController::class, 'pay'])->middleware('throttle:20,1');
    });


    /*
    |--------------------------------------------------------------------------
    | لوحة التحكم — الفنان والإدارة
    |--------------------------------------------------------------------------
    | مشتركة بين الدورين، والفرق نطاق البيانات لا الشاشات: ScopesToArtist
    | تقصر استعلامات الفنان على أعماله بينما يرى المدير كل شيء.
    */
    Route::middleware(['auth:sanctum', 'active', 'role:admin|artist'])
        ->prefix('manage')
        ->group(function (): void {

            Route::get('/dashboard', ManageDashboardController::class);

            // اللوحات
            Route::get('/paintings', [ManagePaintingController::class, 'index']);
            Route::post('/paintings', [ManagePaintingController::class, 'store']);
            Route::get('/paintings/{id}', [ManagePaintingController::class, 'show']);
            Route::put('/paintings/{id}', [ManagePaintingController::class, 'update']);
            Route::delete('/paintings/{id}', [ManagePaintingController::class, 'destroy']);
            Route::post('/paintings/{id}/images', [ManagePaintingController::class, 'uploadImages']);
            Route::delete('/paintings/{id}/images/{imageId}', [ManagePaintingController::class, 'deleteImage']);
            Route::put('/paintings/{id}/images/{imageId}/main', [ManagePaintingController::class, 'setMainImage']);

            // الطلبات
            Route::get('/orders', [ManageOrderController::class, 'index']);
            Route::get('/orders/{orderNumber}', [ManageOrderController::class, 'show']);
            Route::get('/orders/{orderNumber}/transitions', [ManageOrderController::class, 'transitions']);
            Route::put('/orders/{orderNumber}/status', [ManageOrderController::class, 'transition']);

            // الحجوزات
            Route::get('/reservations', [ManageReservationController::class, 'index']);
            Route::get('/reservations/{number}', [ManageReservationController::class, 'show']);
            Route::post('/reservations/{number}/accept', [ManageReservationController::class, 'accept']);
            Route::post('/reservations/{number}/reject', [ManageReservationController::class, 'reject']);
            Route::post('/reservations/{number}/updates', [ManageReservationController::class, 'addUpdate']);
            Route::post('/reservations/{number}/complete', [ManageReservationController::class, 'complete']);
            Route::put('/reservations/{number}/status', [ManageReservationController::class, 'transition']);

            // التصنيفات
            Route::get('/categories', [ManageCategoryController::class, 'index']);
            Route::post('/categories', [ManageCategoryController::class, 'store']);
            Route::put('/categories/{category}', [ManageCategoryController::class, 'update']);
            Route::delete('/categories/{category}', [ManageCategoryController::class, 'destroy']);

            // مراجعة التقييمات
            Route::get('/reviews', [ManageReviewController::class, 'index']);
            Route::put('/reviews/{review}/moderate', [ManageReviewController::class, 'moderate']);
            Route::delete('/reviews/{review}', [ManageReviewController::class, 'destroy']);
        });

    /*
    |--------------------------------------------------------------------------
    | الإدارة فقط
    |--------------------------------------------------------------------------
    | إدارة المنصة نفسها: المستخدمون والصلاحيات والكوبونات والمدفوعات
    | والشحن والإعدادات. الفنان لا يصل إلى أيٍّ منها.
    */
    Route::middleware(['auth:sanctum', 'active', 'role:admin'])
        ->prefix('admin')
        ->group(function (): void {

            Route::get('/users', [AdminUserController::class, 'index']);
            Route::post('/users', [AdminUserController::class, 'store']);
            Route::get('/users/{user}', [AdminUserController::class, 'show']);
            Route::put('/users/{user}', [AdminUserController::class, 'update']);
            Route::put('/users/{user}/toggle-active', [AdminUserController::class, 'toggleActive']);
            Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);

            Route::get('/coupons', [AdminCouponController::class, 'index']);
            Route::post('/coupons', [AdminCouponController::class, 'store']);
            Route::put('/coupons/{coupon}', [AdminCouponController::class, 'update']);
            Route::delete('/coupons/{coupon}', [AdminCouponController::class, 'destroy']);

            Route::get('/payments', [AdminPaymentController::class, 'index']);
            Route::get('/payments/{paymentNumber}/transactions', [AdminPaymentController::class, 'transactions']);
            Route::post('/payments/{paymentNumber}/refund', [AdminPaymentController::class, 'refund']);

            Route::get('/shipping', [AdminShippingController::class, 'index']);
            Route::post('/shipping/zones', [AdminShippingController::class, 'storeZone']);
            Route::put('/shipping/zones/{zone}', [AdminShippingController::class, 'updateZone']);
            Route::delete('/shipping/zones/{zone}', [AdminShippingController::class, 'destroyZone']);
            Route::post('/shipping/rates', [AdminShippingController::class, 'storeRate']);
            Route::put('/shipping/rates/{rate}', [AdminShippingController::class, 'updateRate']);
            Route::delete('/shipping/rates/{rate}', [AdminShippingController::class, 'destroyRate']);

            Route::get('/settings', [AdminSettingController::class, 'index']);
            Route::put('/settings', [AdminSettingController::class, 'update']);
        });

});
