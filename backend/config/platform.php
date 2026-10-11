<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| إعدادات المنصة
|--------------------------------------------------------------------------
|
| هذه قيم الإقلاع فقط. ما يظهر منها في لوحة الإدارة يُخزَّن في جدول
| settings ويُقرأ عبر App\Services\SettingsService، فتبقى هذه الملفات
| مصدر القيمة الافتراضية عند أول تشغيل ولا شيء أكثر.
|
*/

return [

    'urls' => [
        'web' => env('WEB_URL', 'http://localhost:4200'),
        'admin' => env('ADMIN_URL', 'http://localhost:4300'),
    ],

    // ضريبة القيمة المضافة في السعودية ١٥٪ وقت كتابة هذا الملف.
    'tax' => [
        'enabled' => (bool) env('TAX_ENABLED', true),
        'percentage' => (float) env('TAX_PERCENTAGE', 15),
    ],

    'currency' => env('DEFAULT_CURRENCY', 'SAR'),

    /*
    | نُخزّن كل الطوابع الزمنية بـ UTC (انظر config/app.php) ونحوّلها عند
    | العرض فقط. الخلط بين الأمرين هو أصل معظم أخطاء «الطلب ظهر بتاريخ الأمس».
    */
    'timezone' => env('DISPLAY_TIMEZONE', 'Asia/Riyadh'),

    'images' => [
        // نولّد WebP لأن الصور هنا هي المنتج نفسه، وفارق الحجم عن JPEG
        // يظهر مباشرة في زمن تحميل صفحة اللوحة.
        'format' => 'webp',
        'quality' => 82,
        'max_upload_kb' => 8192,
        'variants' => [
            'thumb' => 400,
            'medium' => 900,
            'large' => 1800,
        ],
    ],

];
