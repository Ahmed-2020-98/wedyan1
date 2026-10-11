<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| مشاركة الموارد بين المصادر (CORS)
|--------------------------------------------------------------------------
|
| لا نستخدم '*' هنا. الواجهتان (المتجر ولوحة التحكم) تعملان على نطاقين
| معروفين تأتي قيمهما من FRONTEND_URLS، فأي نطاق آخر يُرفض من المتصفح.
|
| supports_credentials مفعّل لأن Sanctum يحتاج كعكة CSRF في وضع SPA؛
| ومع الكعكات لا يقبل المتصفح '*' في allowed_origins أصلاً.
|
*/

$origins = array_values(array_filter(array_map(
    trim(...),
    explode(',', (string) env('FRONTEND_URLS', 'http://localhost:4200,http://localhost:4300')),
)));

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN'],

    // يقرأها الواجهة لعرض حالة حدّ الطلبات للمستخدم بدل مفاجأته برسالة 429.
    'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'Retry-After'],

    'max_age' => 60 * 60 * 24,

    'supports_credentials' => true,

];
