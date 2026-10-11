# الواجهة البرمجية

كل النقاط تحت `/api/v1`. التوثيق التفاعلي المولَّد من الشيفرة على `/docs/api`
(و`/docs/api.json` بصيغة OpenAPI 3.1).

## الاصطلاحات

- الاستجابة المفردة مغلّفة بـ `{ "data": ... }`، والمُصفّحة تضيف `meta`.
- المفاتيح `camelCase` في الخرج رغم أن الأعمدة `snake_case` — التحويل في `App\Http\Resources`.
- المبالغ تُخرج ككائن `{ amount, currency, formatted }`: الخام للحساب والمنسّق
  للعرض، فلا يختلف تنسيق السعر بين صفحة اللوحة والفاتورة والبريد.
- المصادقة برمز حامل: `Authorization: Bearer {token}`.
- الطوابع الزمنية بـ ISO-8601 وبتوقيت UTC.

## اصطلاح الحماية

| الوسم | المعنى |
|-------|--------|
| — | عام لأي زائر |
| 🔒 | `auth:sanctum` + حساب مفعَّل |
| 🎨 | دور `artist` أو `admin` |
| ⚙️ | دور `admin` فقط |

---

## عام

| | الطريقة | المسار | الوصف |
|---|---------|--------|-------|
| | `GET` | `/health` | فحص جاهزية؛ يتحقق من قاعدة البيانات ويعيد `503` عند تعذّرها |
| | `GET` | `/meta` | العملة والضريبة وسائق الدفع. لا يحوي أي مفتاح سرّي |
| | `POST` | `/newsletter` | اشتراك في النشرة (`throttle:5,1`) |

## المصادقة

| | الطريقة | المسار | الوصف |
|---|---------|--------|-------|
| | `POST` | `/auth/register` | تسجيل زبون. الدور مثبَّت على `customer` ولا يُقرأ من الطلب |
| | `POST` | `/auth/login` | رسالة الفشل واحدة لكل الحالات — لا كشف عن وجود البريد |
| | `POST` | `/auth/forgot-password` | الرد واحد سواء وُجد البريد أم لا |
| | `POST` | `/auth/reset-password` | يُلغي كل الرموز بعد التعيين |
| | `GET` | `/auth/verify-email/{id}/{hash}` | رابط موقّع من البريد، يعيد التوجيه إلى الواجهة |
| 🔒 | `GET` | `/auth/me` | المستخدم الحالي وأدواره |
| 🔒 | `POST` | `/auth/logout` | يُلغي الرمز الحالي وحده |
| 🔒 | `PUT` | `/auth/profile` | تحديث الاسم والجوال |
| 🔒 | `PUT` | `/auth/password` | يُلغي كل الرموز الأخرى ويُصدر رمزاً جديداً |
| 🔒 | `POST` | `/auth/resend-verification` | (`throttle:6,1`) |

كل نقاط الدخول والتسجيل خلف `throttle:10,1`.

## الكتالوج (عام)

| الطريقة | المسار | الوصف |
|---------|--------|-------|
| `GET` | `/home` | كل أقسام الصفحة الرئيسية في طلب واحد، مُخزَّن مؤقتاً ١٠ دقائق |
| `GET` | `/categories` | التصنيفات مع عدد اللوحات المنشورة |
| `GET` | `/categories/{slug}` | تصنيف واحد |
| `GET` | `/paintings` | المعرض — تصفية وترتيب وتصفيح |
| `GET` | `/paintings/filters` | قيم الفلاتر محسوبة من الكتالوج الفعلي |
| `GET` | `/paintings/{slug}` | صفحة اللوحة كاملة |
| `GET` | `/paintings/{slug}/related` | أعمال من التصنيف نفسه |
| `GET` | `/paintings/{slug}/reviews` | المراجعات المعتمدة فقط |

### التصفية والترتيب

```
GET /api/v1/paintings
  ?filter[search]=الصحراء        # العنوان والوصف والرمز والوسوم واسم الفنان
  &filter[category]=تجريدي        # يقبل قائمة مفصولة بفواصل
  &filter[type]=ready_made        # ready_made | reservable | custom_order
  &filter[availability]=available
  &filter[style]=…&filter[material]=…&filter[tag]=…
  &filter[min_price]=1000&filter[max_price]=5000
  &filter[min_width]=40&filter[max_width]=120
  &filter[min_height]=…&filter[max_height]=…
  &filter[featured]=true
  &sort=-published_at             # published_at، price، views_count، sold_count، rating_avg، title
  &page=1&per_page=24             # الحد الأقصى ٤٨
```

> الترتيب غير المُدرَج في `allowedSorts` يُرفض بـ`400` — بما فيه الافتراضي حين
> ترسله الواجهة صراحةً. أي ترتيب جديد يجب إضافته في `PaintingController`.

## السلة والمفضّلة والعناوين 🔒

| الطريقة | المسار |
|---------|--------|
| `GET` `DELETE` | `/cart` |
| `POST` | `/cart/items` · `PUT` `DELETE` `/cart/items/{item}` |
| `POST` `DELETE` | `/cart/coupon` |
| `GET` `POST` | `/wishlist` · `DELETE` `/wishlist/{painting}` · `POST` `/wishlist/{painting}/move-to-cart` |
| `GET` `POST` | `/addresses` · `PUT` `DELETE` `/addresses/{address}` |

`GET /cart` يُزيل تلقائياً ما لم يعد متاحاً ويعيد أسماءه في `removed`.

## إتمام الطلب والطلبات 🔒

| الطريقة | المسار | الوصف |
|---------|--------|-------|
| `GET` | `/checkout/shipping-options?country=SA` | الطرق المتاحة بتكلفتها الفعلية للسلة |
| `POST` | `/checkout` | ينشئ الطلب ويعيد وجهة الدفع (`throttle:20,1`) |
| `GET` | `/orders` · `/orders/{orderNumber}` | طلبات صاحب الحساب وحده |
| `POST` | `/orders/{orderNumber}/cancel` | يُعيد المخزون |

## الحجوزات 🔒

| الطريقة | المسار | الوصف |
|---------|--------|-------|
| `GET` `POST` | `/reservations` | لا سعر عند الطلب — يحدّده الفنان بعد المراجعة |
| `GET` | `/reservations/{number}` | مع سجلّ المراحل المرئي للزبون |
| `POST` | `/reservations/{number}/pay` | المبلغ يُحسب في الخادم ولا يُقرأ من الطلب |

## الحساب والإشعارات والمراجعات 🔒

`/account/overview` · `/account/payments` · `/notifications` ·
`PUT /notifications/{id}/read` · `PUT /notifications/read-all` ·
`/reviews/mine` · `POST /reviews` · `PUT` `DELETE` `/reviews/{review}`

## لوحة التحكم 🎨 — `/manage`

`/dashboard` · اللوحات (`CRUD` + `images`) · الطلبات (`{n}/transitions`، `PUT {n}/status`) ·
الحجوزات (`accept`، `reject`، `updates`، `complete`، `status`) · التصنيفات · المراجعات (`moderate`)

> استعلامات الفنان مقصورة على أعماله عبر `ScopesToArtist`، بينما يرى المدير كل شيء.

## الإدارة ⚙️ — `/admin`

المستخدمون (`toggle-active`) · الكوبونات · المدفوعات (`transactions`، `refund`) ·
الشحن (مناطق وطرق) · الإعدادات

## خارج `/api/v1`

| الطريقة | المسار | الوصف |
|---------|--------|-------|
| `GET` `POST` | `/payments/webhook/{gateway}` | خطّاف المزوّد. معفى من CSRF، ويعيد `200` دائماً |
| `GET` | `/sitemap.xml` | مولّدة من قاعدة البيانات، مُخزَّنة ساعة |
| `GET` `POST` | `/payments/simulator/{payment}` | مسجَّلة فقط حين `PAYMENT_GATEWAY=fake` |
