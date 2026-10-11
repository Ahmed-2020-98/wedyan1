# النشر

## المتطلبات

PHP 8.3+ (`gd`, `intl`, `exif`, `mbstring`, `pdo_mysql`, `zip`) · MySQL 8 ·
Node 22+ لتشغيل تصيير المتجر · Nginx · شهادة TLS

## البنية في الإنتاج

```
                    ┌─ /api/*        → PHP-FPM (Laravel)
                    ├─ /storage/*    → ملفات ثابتة
   Nginx ───────────┼─ /sitemap.xml  → PHP-FPM
   (wedyan.sa)      ├─ /payments/*   → PHP-FPM (خطّافات المزوّد)
                    └─ /*            → Node (تصيير Angular)

   admin.wedyan.sa  ─── ملفات ثابتة + إعادة توجيه كل المسارات إلى index.html
```

خريطة الموقع وخطّافات الدفع تُخدَم من Laravel لأنه يملك البيانات، لكنها يجب
أن تكون على نطاق المتجر نفسه — ولذلك التوجيه أعلاه.

## الخادم

```bash
cd backend
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate

# اضبط: APP_ENV=production، APP_DEBUG=false، APP_URL، DB_*،
#        FRONTEND_URLS، MAIL_*، PAYMENT_GATEWAY=paymob، PAYMOB_*
php artisan migrate --force
php artisan db:seed --class=RoleSeeder --force   # الأدوار والصلاحيات فقط
php artisan storage:link

php artisan config:cache && php artisan route:cache && php artisan view:cache
```

> `db:seed` بلا وسيط يزرع بيانات العرض (٢٤ لوحة مولّدة وحسابات تجريبية).
> في الإنتاج ازرع `RoleSeeder` وحده، ثم أنشئ حساب الفنانة من لوحة الإدارة.

### الطوابير

الإشعارات مؤجَّلة (`ShouldQueue`) لأن إرسال بريد متزامن داخل معالجة الخطّاف
قد يُوقّته فيعيد المزوّد إرساله. بلا عامل لن تصل أي رسالة:

```ini
# /etc/supervisor/conf.d/wedyan-worker.conf
[program:wedyan-worker]
command=php /var/www/wedyan/backend/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=2
user=www-data
```

## الواجهتان

```bash
cd web
npm ci
# اضبط apiUrl و siteUrl في src/environments/environment.ts
npm run build            # ينتج dist/web/{browser,server}

cd ../admin
npm ci
npm run build            # ينتج dist/admin/browser — ملفات ثابتة
```

المتجر يحتاج عملية Node حيّة:

```ini
[program:wedyan-ssr]
command=node /var/www/wedyan/web/dist/web/server/server.mjs
environment=PORT=4000,NODE_ENV=production
autostart=true
autorestart=true
user=www-data
```

## Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name wedyan.sa;
    root /var/www/wedyan/backend/public;

    # الواجهة البرمجية والخطّافات وخريطة الموقع → Laravel
    location ~ ^/(api|payments|sitemap\.xml|storage|docs) {
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # ملفات المتجر المبنيّة، ثم التصيير على Node
    location / {
        root /var/www/wedyan/web/dist/web/browser;
        try_files $uri @ssr;
    }

    location @ssr {
        proxy_pass http://127.0.0.1:4000;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}

server {
    listen 443 ssl http2;
    server_name admin.wedyan.sa;
    root /var/www/wedyan/admin/dist/admin/browser;

    location / { try_files $uri $uri/ /index.html; }
}
```

## بوابة الدفع

في لوحة Paymob اضبط عنوان الخطّاف على:

```
https://wedyan.sa/payments/webhook/paymob
```

ثم انقل من اللوحة إلى `.env`: `PAYMOB_PUBLIC_KEY`، `PAYMOB_SECRET_KEY`،
`PAYMOB_HMAC_SECRET`، و`PAYMOB_INTEGRATION_IDS` (معرّفات طرق الدفع مفصولة
بفواصل)، و`PAYMOB_REGION=ksa` أو `egypt`.

`PAYMENT_GATEWAY=paymob` يُلغي تسجيل صفحة المحاكاة تماماً.

## بعد كل نشر

```bash
php artisan migrate --force
php artisan config:cache && php artisan route:cache
php artisan queue:restart          # ليلتقط العمّال الشيفرة الجديدة
php artisan cache:clear            # الصفحة الرئيسية وخريطة الموقع مُخزَّنتان
```

## التخزين

`FILESYSTEM_DISK=public` محلياً. للانتقال إلى S3 أو CDN غيّرها إلى `s3` واضبط
`AWS_*` — لا سطر برمجي يتغيّر، لأن كل وصول للملفات يمرّ عبر أقراص Laravel.

**النسخ الاحتياطي يجب أن يشمل `storage/app/public`** لا قاعدة البيانات وحدها:
صور اللوحات هي المنتج، وفقدانها يعني فقدان المعرض.

## فحص الصحة

`GET /api/v1/health` يفتح اتصالاً فعلياً بقاعدة البيانات ويعيد `503` عند
تعذّرها — صالح لموازن الحمل.
