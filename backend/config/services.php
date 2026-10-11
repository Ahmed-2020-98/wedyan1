<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | بوابة الدفع
    |--------------------------------------------------------------------------
    | الطبقة معزولة خلف واجهة PaymentGateway، فتغيير المزوّد لاحقاً يعني
    | إضافة صنف واحد وتغيير PAYMENT_GATEWAY — لا أكثر.
    |
    | driver=fake هو الافتراضي، فيعمل المشروع كاملاً بلا أي حساب لدى المزوّد.
    */

    'payment' => [
        'driver' => env('PAYMENT_GATEWAY', 'fake'),
        'currency' => env('DEFAULT_CURRENCY', 'SAR'),
    ],

    /*
    | Paymob تعمل بنطاق مختلف لكل دولة، والمفاتيح تختلف باختلافه.
    | مرجع: https://developers.paymob.com
    */
    'paymob' => [
        'region' => env('PAYMOB_REGION', 'ksa'),
        'base_urls' => [
            'ksa' => 'https://ksa.paymob.com',
            'egypt' => 'https://accept.paymob.com',
            'uae' => 'https://uae.paymob.com',
            'oman' => 'https://oman.paymob.com',
        ],
        'currencies' => [
            'ksa' => 'SAR',
            'egypt' => 'EGP',
            'uae' => 'AED',
            'oman' => 'OMR',
        ],
        'public_key' => env('PAYMOB_PUBLIC_KEY'),
        'secret_key' => env('PAYMOB_SECRET_KEY'),
        'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
        'integration_ids' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('PAYMOB_INTEGRATION_IDS', '')),
        ))),
    ],

];
