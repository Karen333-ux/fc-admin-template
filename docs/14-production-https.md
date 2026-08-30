# ١٤ — الإنتاج و HTTPS

## المبدأ

> **HTTPS من يوم ١، مش يوم آخر.** أي حاجة اتبنت على HTTP هتكسر لما تتحول.

---

## ١. أشهر باگ في الإنتاج: TrustProxies

لو التطبيق ورا load balancer أو reverse proxy بينهي الـ TLS، Laravel بيشوف الطلب كـ HTTP — فكل الـ URLs والأصول بتتولّد `http://` وبتتكسر.

في `bootstrap/app.php`:

```php
use Illuminate\Http\Request;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(
        at: env('TRUSTED_PROXIES', '*'),        // أو قائمة CIDR صريحة
        headers: Request::HEADER_X_FORWARDED_FOR
               | Request::HEADER_X_FORWARDED_HOST
               | Request::HEADER_X_FORWARDED_PORT
               | Request::HEADER_X_FORWARDED_PROTO,
    );

    $middleware->web(append: [
        SetLocale::class,
        AssignRequestContext::class,
        SecurityHeaders::class,
    ]);
})
```

> ⚠️ `at: '*'` مقبول لو الـ LB هو المنفذ الوحيد للتطبيق. لو التطبيق متاح مباشرة كمان، حط CIDR صريح — وإلا حد يقدر يزوّر `X-Forwarded-For`.

وفي `AppServiceProvider::boot()`:

```php
if ($this->app->isProduction()) {
    URL::forceScheme('https');
}
```

---

## ٢. ملف .env للإنتاج

```dotenv
APP_NAME="Future Code Admin"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://admin.example.com
APP_KEY=base64:...
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en
APP_TIMEZONE=Africa/Cairo
APP_VERSION=1.0.0

TRUSTED_PROXIES=10.0.0.0/8

DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=5432
DB_DATABASE=fc_admin
DB_USERNAME=...
DB_PASSWORD=...

REDIS_HOST=...
REDIS_PASSWORD=...
REDIS_PORT=6379
REDIS_CLIENT=phpredis

CACHE_STORE=redis
CACHE_PREFIX=fc_admin
QUEUE_CONNECTION=redis
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_ENCRYPT=true
SESSION_DOMAIN=.example.com

BROADCAST_CONNECTION=reverb
REVERB_APP_KEY=...
REVERB_HOST=ws.example.com
REVERB_SCHEME=https

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=eu-central-1
AWS_BUCKET=fc-admin-prod
AWS_PRIVATE_BUCKET=fc-admin-prod-private
AWS_URL=https://cdn.example.com

LOG_CHANNEL=stack
LOG_STACK=json,stderr,sentry
LOG_LEVEL=info

SENTRY_LARAVEL_DSN=...
SENTRY_TRACES_SAMPLE_RATE=0.2

PERMISSION_CACHE_TTL="24 hours"
```

> **قاعدة:** أي مفتاح هنا لازم يكون في `.env.example` بقيمة وهمية.

---

## ٣. سكربت النشر

`deploy.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail

echo "→ وضع الصيانة"
php artisan down --render="errors::503" --retry=60 --secret="$DEPLOY_SECRET"

echo "→ سحب الكود"
git pull origin main

echo "→ الاعتماديات"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "→ الأصول"
npm ci
npm run build

echo "→ الميجريشنز"
php artisan migrate --force

echo "→ مزامنة الصلاحيات"
php artisan authorization:sync

echo "→ مسح الكاش القديم"
php artisan optimize:clear

echo "→ بناء الكاش"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan filament:optimize
php artisan icons:cache

echo "→ إعادة تشغيل العمال"
php artisan horizon:terminate
php artisan queue:restart

echo "→ إنهاء الصيانة"
php artisan up

echo "✔ تم النشر"
```

### ترتيب مهم

- `migrate` **قبل** `authorization:sync` (الجداول لازم تكون موجودة)
- `optimize:clear` **قبل** بناء الكاش الجديد
- `horizon:terminate` **بعد** كل حاجة (عشان العمال ياخدوا الكود الجديد)
- `php artisan up` آخر حاجة

---

## ٤. قائمة فحص ما قبل الإطلاق

### التطبيق
- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] `APP_KEY` مولّد ومحفوظ في مكان آمن
- [ ] `APP_URL` بـ `https://`
- [ ] Telescope مش مثبّت (`composer show | grep telescope` = فاضي)
- [ ] كل الكاشات مبنية
- [ ] OPcache مفعّل: `opcache.enable=1`, `opcache.validate_timestamps=0`
- [ ] `composer install --no-dev` (مفيش حزم تطوير)

### الأمان
- [ ] HTTPS مفروض + شهادة صالحة + تجديد تلقائي
- [ ] HSTS مفعّل
- [ ] كل الرؤوس الأمنية موجودة (افحص بـ securityheaders.com — الهدف A+)
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] `TRUSTED_PROXIES` محدد صح
- [ ] Rate limiting شغّال
- [ ] `/health` محمي بمفتاح
- [ ] Horizon و Pulse محميين بصلاحيات
- [ ] عارض السجلات للـ super_admin بس

### قاعدة البيانات
- [ ] كل الميجريشنز اتنفّذت
- [ ] الفهارس موجودة على كل عمود بحث/ترتيب
- [ ] `pg_trgm` مفعّل لو بنستخدم بحث نصي
- [ ] النسخ الاحتياطي مجدول ومُختبَر استرجاعه
- [ ] Connection pooling (PgBouncer) لو الحمل عالي
- [ ] `DatabaseConnectionCountCheck` مضبوط على الحد الفعلي

### الطوابير
- [ ] Supervisor بيشغّل Horizon وبيعيد تشغيله تلقائياً
- [ ] Cron للجدولة شغّال — تأكد بـ `php artisan schedule:list`
- [ ] كل الطوابير ليها عمال

### المراقبة
- [ ] Sentry بيستقبل — جرّب خطأ متعمّد
- [ ] Uptime monitor على `/health`
- [ ] تنبيهات على: فشل النسخ الاحتياطي، امتلاء القرص، تراكم الطابور، معدل الأخطاء
- [ ] اللوجات بتتجمّع في مكان مركزي

### الوظائف
- [ ] تسجيل دخول + 2FA
- [ ] رفع ملف لـ S3 والرابط شغّال
- [ ] إرسال بريد فعلي وصل
- [ ] إشعار فوري بيوصل بالبث
- [ ] تبديل اللغة والمستأجر
- [ ] كل الأدوار مختبرة يدوياً

---

## ٥. الأداء

### PHP-FPM
```ini
pm = dynamic
pm.max_children = 40          ; اضبطها على (RAM المتاح / متوسط استهلاك العملية)
pm.start_servers = 8
pm.min_spare_servers = 4
pm.max_spare_servers = 12
pm.max_requests = 500
```

### OPcache
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0     ; ← مهم: مفيش فحص للتغيير في الإنتاج
opcache.jit=tracing
opcache.jit_buffer_size=64M
```

> `validate_timestamps=0` معناه إن الكود الجديد مش هيظهر غير بعد `opcache_reset()` أو إعادة تشغيل FPM. حط ده في سكربت النشر.

### Redis
```
maxmemory 2gb
maxmemory-policy allkeys-lru
appendonly yes
```

> **مهم:** لو Redis بتاع الكاش هو نفسه بتاع الطوابير، `allkeys-lru` ممكن يمسح jobs. **افصلهم** — قاعدتين مختلفتين على الأقل (`REDIS_CACHE_DB=1`, `REDIS_QUEUE_DB=2`)، والأفضل instances منفصلة.

### CDN
- الأصول (`build/`) على CDN
- الصور العامة من S3 عبر CloudFront
- `Cache-Control: public, max-age=31536000, immutable` للأصول المُبصمة

---

## ٦. الاستضافة

الخيارات المطروحة (القرار في أسبوع ٥):

| الخيار | مناسب لـ | ملاحظة |
|---|---|---|
| **Coolify** على VPS | مشاريعنا الحالية | تحكم كامل، تكلفة قليلة، بنعرفه |
| **Laravel Forge** + DigitalOcean | فريق أكبر | أسهل، اشتراك شهري |
| **Docker + K8s** | لو وصلنا لعدة عملاء كبار | تعقيد عالي — مش دلوقتي |

أياً كان الاختيار، لازم:
- تجديد شهادة تلقائي (Let's Encrypt)
- نشر بلا توقّف (zero-downtime) أو نافذة صيانة قصيرة
- استرجاع سريع (rollback) لآخر إصدار

---

## ٧. معايير القبول

- [ ] النشر على staging شغّال بالسكربت وبالكامل
- [ ] `curl -I https://staging...` بيرجّع الرؤوس الأمنية كلها
- [ ] securityheaders.com بيدي A أو A+
- [ ] كل بنود قائمة ما قبل الإطلاق متعلّم عليها
- [ ] الرجوع للإصدار السابق (rollback) مُجرَّب فعلياً
- [ ] وقت استجابة الصفحة الرئيسية < 300ms على staging
- [ ] Redis الكاش والطوابير منفصلين
