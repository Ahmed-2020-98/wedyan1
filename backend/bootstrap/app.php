<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
        ]);

        /*
         | ترويسات أمان على كل رد. القيم محافظة عمداً لأن الخادم يخدم
         | واجهة برمجية فقط — لا صفحات HTML تحتاج تحميل موارد خارجية.
         */
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        /*
         | خطّافات الدفع تأتي من خادم المزوّد لا من متصفح فيه جلسة، فلا
         | يوجد رمز CSRF أصلاً ليُرسل. التحقق هنا بتوقيع HMAC — انظر
         | PaymobGateway::verifySignature — وهو أقوى من الرمز في هذا السياق.
         */
        $middleware->validateCsrfTokens(except: [
            'payments/webhook/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
