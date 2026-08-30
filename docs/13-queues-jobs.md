# ١٣ — الطوابير والمهام المجدولة

## المبدأ

> **أي عملية بتاخد أكتر من ٢٠٠ms تروح الطابور.**

المستخدم ما يستناش. الرفع، التحويلات، البريد، التصدير، التقارير — كلها async.

---

## ١. Horizon

```bash
composer require laravel/horizon:^5.48
php artisan horizon:install
```

`config/horizon.php`:

```php
'environments' => [

    'production' => [
        'supervisor-critical' => [
            'connection'   => 'redis',
            'queue'        => ['critical'],
            'balance'      => 'auto',
            'minProcesses' => 2,
            'maxProcesses' => 10,
            'tries'        => 3,
            'timeout'      => 60,
        ],
        'supervisor-notifications' => [
            'connection'   => 'redis',
            'queue'        => ['notifications'],
            'balance'      => 'auto',
            'minProcesses' => 2,
            'maxProcesses' => 8,
            'tries'        => 5,
            'timeout'      => 120,
        ],
        'supervisor-media' => [
            'connection'   => 'redis',
            'queue'        => ['media'],
            'balance'      => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 6,
            'tries'        => 3,
            'timeout'      => 600,       // التحويلات بتاخد وقت
            'memory'       => 512,
        ],
        'supervisor-default' => [
            'connection'   => 'redis',
            'queue'        => ['default', 'exports'],
            'balance'      => 'auto',
            'minProcesses' => 1,
            'maxProcesses' => 6,
            'tries'        => 3,
            'timeout'      => 300,
        ],
    ],

    'local' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue'      => ['critical', 'notifications', 'media', 'exports', 'default'],
            'balance'    => 'auto',
            'maxProcesses' => 3,
        ],
    ],
],
```

### الطوابير وأولوياتها

| الطابور | الاستخدام | الأولوية |
|---|---|---|
| `critical` | عمليات مالية، أمان | ١ |
| `notifications` | كل الإشعارات | ٢ |
| `media` | التحويلات، الرفع لـ S3 | ٣ |
| `exports` | التصدير والتقارير | ٤ |
| `default` | الباقي | ٥ |

### الدخول لـ Horizon

```php
// HorizonServiceProvider
Gate::define('viewHorizon', fn ($user) => Gate::allows('access.horizon'));
```

ولوّح صفحة داخل Filament تعمل embed أو redirect — بصلاحية `access.horizon`.

---

## ٢. قواعد كتابة الـ Job

```php
final class GenerateMonthlyReportJob extends ContextAwareJob
{
    public int $tries = 3;
    public int $timeout = 600;
    public array $backoff = [30, 120, 600];
    public int $maxExceptions = 2;

    public function __construct(
        public readonly int $tenantId,
        public readonly string $month,
    ) {
        parent::__construct();
        $this->onQueue('exports');
    }

    public function handle(TenantContext $context, ReportGenerator $generator): void
    {
        $context->set($this->tenantId);

        $path = $generator->monthly($this->month);

        Notification::make()
            ->success()
            ->title(__('reports.ready'))
            ->actions([Action::make('download')->url($path)])
            ->sendToDatabase(User::find($this->userId), isEventDispatched: true);
    }

    public function failed(Throwable $e): void
    {
        Log::channel('audit')->error('فشل توليد التقرير الشهري', [
            'tenant_id' => $this->tenantId,
            'month'     => $this->month,
            'error'     => $e->getMessage(),
        ]);

        Notification::make()
            ->danger()
            ->title(__('reports.failed'))
            ->sendToDatabase(User::find($this->userId));
    }

    public function uniqueId(): string
    {
        return "report:{$this->tenantId}:{$this->month}";
    }
}
```

### قواعد إلزامية

1. **`tenantId` صريح** في الكونستركتور — الـ Job مش شايف السياق
2. **`$tries` و`$timeout` و`$backoff`** محددين صراحةً — مفيش اعتماد على الافتراضي
3. **`failed()` موجودة دايماً** — بتسجّل وبتبلّغ
4. **`ShouldBeUnique`** لأي Job ممكن يتكرر
5. **مرّر IDs مش موديلات** — الموديل بيتسلسل وممكن يبقى قديم
6. **`->afterCommit()`** لأي Job بيتبعت من جوه ترانزاكشن

---

## ٣. المهام المجدولة

في `routes/console.php` (Laravel 11+):

```php
use Illuminate\Support\Facades\Schedule;

// النسخ الاحتياطي
Schedule::command('backup:clean')->dailyAt('01:00')->onOneServer();
Schedule::command('backup:run')->dailyAt('01:30')->onOneServer()->runInBackground();
Schedule::command('backup:monitor')->dailyAt('02:00')->onOneServer();

// التنظيف
Schedule::command('notifications:prune')->dailyAt('03:00')->onOneServer();
Schedule::command('activitylog:clean')->weeklyOn(1, '03:30')->onOneServer();
Schedule::command('telescope:prune --hours=48')->daily()->onOneServer();
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// الصحة
Schedule::command('health:check')->everyFifteenMinutes()->onOneServer();

// مهام لكل مستأجر
Schedule::call(function (TenantContext $context) {
    $context->forEachTenant(function (Tenant $tenant): void {
        GenerateWeeklyDigestJob::dispatch($tenant->id);
    });
})->weeklyOn(0, '08:00')->onOneServer()->name('weekly-digests');

// مهام الاشتراكات
Schedule::command('tenants:check-trials')->dailyAt('06:00')->onOneServer();
```

### قواعد

- **`->onOneServer()`** على كل مهمة — لو فيه أكتر من سيرفر، متتنفّذش مرتين
- **`->withoutOverlapping()`** لأي مهمة طويلة
- **`->name()`** لأي `Schedule::call()` — عشان `onOneServer` يشتغل
- **`->runInBackground()`** للمهام الطويلة عشان ماتعطّلش الجدول
- **مفيش منطق في الجدول** — الجدول بيستدعي Command أو يـ dispatch Job

### التشغيل

```bash
# في الإنتاج — cron واحد
* * * * * cd /var/www/app && php artisan schedule:run >> /dev/null 2>&1
```

أو `php artisan schedule:work` في حاوية منفصلة.

---

## ٤. Supervisor في الإنتاج

`/etc/supervisor/conf.d/fc-horizon.conf`:

```ini
[program:fc-horizon]
process_name=%(program_name)s
command=php /var/www/app/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/app/storage/logs/horizon.log
stopwaitsecs=3600
```

> `stopwaitsecs=3600` مهم — بيدي الـ jobs الجارية وقت تخلص قبل ما Horizon يقفل.

في النشر: `php artisan horizon:terminate` بعد الديبلوي عشان الـ workers ياخدوا الكود الجديد.

---

## ٥. الفشل والمراقبة

```php
// قاعدة بيانات الـ failed jobs
php artisan queue:failed-table
php artisan migrate
```

- Horizon بيعرض الفاشلة مع الـ stack trace
- تنبيه Sentry عند فشل أي job في `critical`
- صفحة Filament للـ failed jobs مع زرار «إعادة المحاولة» بصلاحية `access.horizon`
- `health` check بيفشل لو عدد الفاشلة > ٥٠

```php
Schedule::command('queue:prune-failed --hours=168')->weekly();
```

---

## ٦. معايير القبول

- [ ] ٥ طوابير معرّفة بأولويات واضحة في `horizon.php`
- [ ] كل Job فيه `tenantId` و`tries` و`timeout` و`failed()`
- [ ] كل مهمة مجدولة عليها `onOneServer()`
- [ ] Horizon شغّال ومحمي بصلاحية
- [ ] Supervisor مظبوط بـ `stopwaitsecs`
- [ ] `horizon:terminate` في سكربت النشر
- [ ] Job فاشل بيوصل Sentry وبيظهر في Horizon
- [ ] رفع صورة كبيرة: الاستجابة فورية والتحويلات في الطابور
- [ ] اختبار Pest: Job بيشتغل بالمستأجر الصح
