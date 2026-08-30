# ١١ — اللوجينج والمراقبة

## المبدأ

> **أي سطر لوج من غير سياق = سطر ضايع.**

كل سطر لوج لازم يجاوب: **مين؟ إمتى؟ أي مستأجر؟ أي طلب؟** من غير كده، لما مشكلة تحصل في الإنتاج هتقعد ساعتين تدوّر.

---

## ١. اللوجينج المهيكل (JSON)

في `config/logging.php`:

```php
'channels' => [

    'stack' => [
        'driver'            => 'stack',
        'channels'          => explode(',', env('LOG_STACK', 'daily')),
        'ignore_exceptions' => false,
    ],

    'daily' => [
        'driver'     => 'daily',
        'path'       => storage_path('logs/laravel.log'),
        'level'      => env('LOG_LEVEL', 'debug'),
        'days'       => env('LOG_DAILY_DAYS', 14),
        'replace_placeholders' => true,
    ],

    // القناة الأساسية في الإنتاج
    'json' => [
        'driver'    => 'monolog',
        'level'     => env('LOG_LEVEL', 'info'),
        'handler'   => Monolog\Handler\RotatingFileHandler::class,
        'handler_with' => [
            'filename' => storage_path('logs/app.json'),
            'maxFiles' => 30,
        ],
        'formatter' => Monolog\Formatter\JsonFormatter::class,
        'processors' => [
            App\Logging\Processors\ContextProcessor::class,
            Monolog\Processor\IntrospectionProcessor::class,
            [
                'processor' => Monolog\Processor\PsrLogMessageProcessor::class,
                'with'      => ['removeUsedContextFields' => true],
            ],
        ],
    ],

    // للحاويات — stdout عشان Docker يلمّه
    'stderr' => [
        'driver'    => 'monolog',
        'handler'   => Monolog\Handler\StreamHandler::class,
        'handler_with' => ['stream' => 'php://stderr'],
        'formatter' => Monolog\Formatter\JsonFormatter::class,
        'level'     => env('LOG_LEVEL', 'info'),
    ],

    // قناة تدقيق منفصلة — الاحتفاظ أطول
    'audit' => [
        'driver'    => 'monolog',
        'handler'   => Monolog\Handler\RotatingFileHandler::class,
        'handler_with' => [
            'filename' => storage_path('logs/audit.json'),
            'maxFiles' => 365,
        ],
        'formatter' => Monolog\Formatter\JsonFormatter::class,
    ],

    'sentry' => [
        'driver' => 'sentry',
        'level'  => 'error',
    ],
],
```

في الإنتاج: `LOG_CHANNEL=stack` و `LOG_STACK=json,stderr,sentry`.

---

## ٢. السياق التلقائي في كل سطر

```php
namespace Src\Support\Presentation\Http\Middleware;

final class AssignRequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id') ?? (string) Str::uuid();

        Log::shareContext([
            'request_id' => $requestId,
            'user_id'    => auth()->id(),
            'tenant_id'  => app(TenantContext::class)->id(),
            'ip'         => $request->ip(),
            'method'     => $request->method(),
            'path'       => $request->path(),
            'locale'     => app()->getLocale(),
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
```

### للطوابير — السياق مش بيعدّي الحدود

اعمل base job:

```php
abstract class ContextAwareJob implements ShouldQueue
{
    public ?string $requestId = null;
    public ?int $tenantId = null;
    public ?int $userId = null;

    public function __construct()
    {
        $this->requestId = Log::sharedContext()['request_id'] ?? (string) Str::uuid();
        $this->tenantId  = app(TenantContext::class)->id();
        $this->userId    = auth()->id();
    }

    public function middleware(): array
    {
        return [new RestoresLogContext()];
    }
}
```

الـ middleware بيرجّع السياق قبل تنفيذ الـ Job — فالسلسلة كاملة من الطلب للـ Job في اللوج بنفس `request_id`. ده اللي بيخلّي تتبّع مشكلة في الإنتاج يوصل لدقايق بدل ساعات.

---

## ٣. متى تسجّل وبأي مستوى

| المستوى | متى | مثال |
|---|---|---|
| `debug` | تطوير فقط | قيم متغيرات |
| `info` | حدث أعمال طبيعي | «تم إنشاء مستخدم» |
| `notice` | حدث غير عادي بس مش مشكلة | «إعادة محاولة نجحت» |
| `warning` | حاجة محتاجة انتباه | «تخزين وصل ٨٠٪» |
| `error` | فشل عملية | «فشل إرسال بريد» |
| `critical` | جزء من النظام واقع | «قاعدة البيانات مش متاحة» |
| `alert` | لازم تدخّل فوري | «كل الطوابير واقفة» |

### ممنوعات
- ❌ **متسجّلش** كلمات مرور، توكنز، أرقام بطاقات، بيانات شخصية حساسة
- ❌ **متسجّلش** كامل الـ request body من غير تنقية
- ❌ **متسجّلش** في لوب — لوّج مرة بالعدد الإجمالي

```php
// config/logging.php أو middleware
'redact' => ['password', 'password_confirmation', 'token', 'api_key', 'secret', 'authorization'],
```

---

## ٤. سجل النشاط (Activity Log)

```bash
composer require spatie/laravel-activitylog:^5.1
composer require pxlrbt/filament-activity-log:^3.1
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan migrate
```

> ⚠️ الإصدار 5.x بيتطلب **Laravel 13 + PHP 8.4**. لو ثابتين على PHP 8.3، ثبّت `^4.12`.

```php
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'status', 'locale'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('identity')
            ->setDescriptionForEvent(fn (string $event) => __("audit.events.user.{$event}"));
    }

    public function tapActivity(Activity $activity): void
    {
        $activity->properties = $activity->properties->merge([
            'tenant_id'  => app(TenantContext::class)->id(),
            'ip'         => request()->ip(),
            'request_id' => Log::sharedContext()['request_id'] ?? null,
        ]);
    }
}
```

### ما يجب تسجيله دايماً
- تغيير صلاحية أو دور
- حذف أي سجل
- انتحال الشخصية (دخول وخروج)
- تغيير إعدادات النظام
- أي عملية مالية
- تسجيل دخول فاشل متكرر
- تصدير بيانات

### التنظيف

```php
// أمر مجدول أسبوعي
Activity::where('created_at', '<', now()->subMonths(config('activitylog.retention_months', 12)))
    ->whereNotIn('log_name', ['security', 'financial'])   // دول بيتحفظوا للأبد
    ->delete();
```

---

## ٥. عارض السجلات في اللوحة

```bash
composer require opcodesio/log-viewer
```

> ⚠️ **تحقّق أولاً:** افتح README الباكدج وشوف هل فيه تكامل Filament مباشر ولا محتاج wrapper زي `achyutn/filament-log-viewer`. متثبّتش wrapper من غير ما تتأكد إنه بيدعم Filament v5.

الصفحة محمية بـ `access.log_viewer`، ولازم:
- تكون **مقروءة فقط**
- تنقّي الأسرار قبل العرض
- متكونش متاحة للـ `admin` العادي — للـ `super_admin` بس

---

## ٦. مراقبة الأخطاء (Sentry)

```bash
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=...
```

```php
// config/sentry.php
'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.2),
'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE', 0.1),
'send_default_pii' => false,        // ← إلزامي: مانبعتش بيانات شخصية
```

إثراء السياق:

```php
// في middleware أو AppServiceProvider
\Sentry\configureScope(function (Scope $scope): void {
    $scope->setTag('tenant_id', (string) app(TenantContext::class)->id());
    $scope->setUser(['id' => auth()->id()]);   // ID بس، مش الإيميل
});
```

---

## ٧. صحة النظام

```bash
composer require spatie/laravel-health:^1.40
```

```php
Health::checks([
    DatabaseCheck::new(),
    RedisCheck::new(),
    CacheCheck::new(),
    QueueCheck::new()->onQueue(['default', 'notifications']),
    HorizonCheck::new(),
    UsedDiskSpaceCheck::new()->warnWhenUsedSpaceIsAbovePercentage(70)
                             ->failWhenUsedSpaceIsAbovePercentage(85),
    ScheduleCheck::new(),
    DatabaseConnectionCountCheck::new()->warnWhenMoreConnectionsThan(50),
    BackupsCheck::new()->locatedAt(storage_path('backups')),
    OptimizedAppCheck::new(),             // بيتأكد config:cache شغّال
    DebugModeCheck::new(),                // بيفشل لو APP_DEBUG=true في الإنتاج
    EnvironmentCheck::new(),
]);
```

صفحة `HealthPage` في Filament محمية بـ `access.health`، وendpoint `/health` بدون مصادقة (بس بمفتاح) للـ uptime monitor.

---

## ٨. Pulse و Telescope

```bash
composer require laravel/pulse:^1.8              # إنتاج — خفيف
composer require laravel/telescope:^5.22 --dev   # تطوير فقط
```

> ⚠️ **Telescope ممنوع في الإنتاج.** ثبّته بـ `--dev` وتأكد إن `TelescopeServiceProvider` بيتسجّل في البيئة المحلية بس. Telescope في الإنتاج = تسريب بيانات + بطء.

Pulse بيتحط في اللوحة كـ صفحة مخصصة بصلاحية `access.pulse`.

---

## ٩. النسخ الاحتياطي

```bash
composer require spatie/laravel-backup:^10.3
```

```php
// config/backup.php
'destination' => ['disks' => ['s3-backups']],
'notifications' => ['mail' => ['to' => env('BACKUP_ALERT_EMAIL')]],
```

```php
// routes/console.php
Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run')->daily()->at('01:30');
Schedule::command('backup:monitor')->daily()->at('02:00');
```

> **قاعدة:** نسخة احتياطية مش مُختبَرة = مفيش نسخة احتياطية. مرة كل شهر، نجرّب استرجاع فعلي على بيئة منفصلة ونوثّق النتيجة.

---

## ١٠. معايير القبول

- [ ] كل سطر لوج في الإنتاج JSON وفيه `request_id` + `user_id` + `tenant_id`
- [ ] الـ `request_id` بيتنقل من الطلب للـ Job — مؤكد بتتبّع فعلي
- [ ] مفيش كلمات مرور أو توكنز في اللوج — `grep -i password storage/logs/` = صفر
- [ ] كل العمليات الحساسة مسجّلة في activitylog
- [ ] Sentry بيستقبل خطأ متعمّد بكل السياق
- [ ] `/health` بيرجّع JSON بحالة كل فحص
- [ ] Telescope مش مثبّت في الإنتاج — `composer show` بيثبت
- [ ] النسخ الاحتياطي بيروح S3 يومياً، والاسترجاع مُختبَر ومُوثّق
- [ ] أوامر التنظيف مجدولة وشغّالة
