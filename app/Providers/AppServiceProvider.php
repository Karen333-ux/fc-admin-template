<?php

declare(strict_types=1);

namespace App\Providers;

use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Actions\LogActivityAction;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Spatie\Activitylog\Models\Activity;
use Spatie\Health\Checks\Checks\BackupsCheck;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DatabaseConnectionCountCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\RedisCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;
use Src\Contexts\Settings\Infrastructure\Locale\SettingsLocaleDefaults;
use Src\Contexts\Settings\Infrastructure\Storage\SettingsStoragePreferences;
use Src\Support\Application\Contracts\DiskResolver;
use Src\Support\Application\Contracts\LocaleDefaults;
use Src\Support\Application\Contracts\NotificationChannels;
use Src\Support\Application\Contracts\PanelAccess as PanelAccessContract;
use Src\Support\Application\Contracts\StoragePreferences;
use Src\Support\Application\Contracts\TenantContext as TenantContextContract;
use Src\Support\Infrastructure\ActivityLog\ActivityLogContext;
use Src\Support\Infrastructure\Authorization\ImpersonationContext;
use Src\Support\Infrastructure\Authorization\InvariantRegistry;
use Src\Support\Infrastructure\Authorization\PanelAccess;
use Src\Support\Infrastructure\Authorization\PermissionBuilder;
use Src\Support\Infrastructure\Authorization\TenantBoundary;
use Src\Support\Infrastructure\Filesystem\MediaOwnership;
use Src\Support\Infrastructure\Filesystem\SettingsDrivenDiskResolver;
use Src\Support\Infrastructure\Health\FailedJobsCountCheck;
use Src\Support\Infrastructure\Http\ForceHttpsInProduction;
use Src\Support\Infrastructure\Logging\Redactor;
use Src\Support\Infrastructure\Notifications\NotificationChannelResolver;
use Src\Support\Infrastructure\Notifications\NotificationOwnership;
use Src\Support\Infrastructure\Queue\TagFailedJobForSentry;
use Src\Support\Infrastructure\Tenancy\TenantContext;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ⚠️ singleton إلزامي. bind عادي بيدّي نسخة فاضية كل مرة
        // والعزل بيقع بصمت. (docs/03 بند ٣ · docs/22 بند ٥)
        //
        // ⚠️ ترتيب معاملات alias() مهم. الشكل المكتوب في docs/03 و docs/22:
        //     singleton(Contract::class, Concrete::class);
        //     alias(Contract::class, Concrete::class);      ← بيعمل تكرار لا نهائي
        // لأن alias($abstract, $alias) بيسجّل aliases[$alias] = $abstract،
        // يعني الاسم المحسوس بيرجّع للعقد، والعقد بيبني الاسم المحسوس → حلقة.
        // الشكل الصح: العقد alias على المحسوس. النية زي ما هي — الاتنين
        // بيرجّعوا نفس الـ singleton. (اتكشف في Phase 4)
        $this->app->singleton(TenantContext::class);
        $this->app->alias(TenantContext::class, TenantContextContract::class);

        // الدخول للوحة بيتفحص قبل ما يتحدد مستأجر، فالفحص بيدور على
        // مستأجري المستخدم واحد واحد. (ADR-026)
        $this->app->singleton(PanelAccessContract::class, PanelAccess::class);

        // نظام الملفات: العقد في Support، والتنفيذ اللي بيقرا الإعدادات في
        // سياق Settings — الاتجاه ده بيحافظ على ADR-011. (ADR-022)
        $this->app->singleton(StoragePreferences::class, SettingsStoragePreferences::class);
        $this->app->singleton(DiskResolver::class, SettingsDrivenDiskResolver::class);
        $this->app->singleton(MediaOwnership::class);

        // الإشعارات: العقد في Support والتنفيذ جنبه — مفيش استيراد
        // من Contexts، فـ ADR-011 محفوظ.
        // اللغة الافتراضية: نفس عكس الاتجاه — Identity بيشوف العقد بس
        $this->app->singleton(LocaleDefaults::class, SettingsLocaleDefaults::class);

        $this->app->singleton(NotificationChannels::class, NotificationChannelResolver::class);
        $this->app->singleton(NotificationOwnership::class);

        $this->app->singleton(Redactor::class);
        $this->app->singleton(ActivityLogContext::class);

        $this->app->singleton(InvariantRegistry::class);
        $this->app->singleton(TenantBoundary::class);
        $this->app->singleton(ImpersonationContext::class);
        $this->app->singleton(PermissionBuilder::class);
    }

    public function boot(): void
    {
        // ميجريشنز النواة المشتركة: tenants + tenant_user (ADR-011)
        $this->loadMigrationsFrom(
            base_path('src/Support/Infrastructure/Database/Migrations'),
        );

        $this->forgetTenantContextBetweenJobs();
        $this->configureTableDefaults();
        $this->enrichActivityLog();
        $this->configurePasswordDefaults();
        $this->configureHealthChecks();
        $this->tagFailedJobsForSentry();
        $this->configureTrustedProxies();
        $this->forceHttpsInProduction();
    }

    /**
     * الإعدادات الافتراضية لكل جداول اللوحة. (docs/08 بند ١)
     *
     * بدل ما نكرّر نفس السطور في ٦٠ مورد، بنظبطها مرة واحدة هنا. المورد
     * اللي عايز يخالف بيكتب المخالفة عنده — و**بتغلب** الافتراضي:
     * `Table::make()` بيشغّل الكلوجر ده وقت الإنشاء، وبعدها بيتنادى
     * `table()` بتاع المورد، فآخر كلام هو كلام المورد.
     * (متحقّق من `vendor/filament/tables/src/Table.php` سطر ٧٥ و
     * `Concerns/InteractsWithTable.php` سطر ١٨٢)
     *
     * ⚠️ **النطاق: العملية كلها، مش لوحة بعينها.** `configureUsing()`
     *    بيخزّن الكلوجر في `ComponentManager` بمفتاح اسم الكلاس بس، فأي
     *    جدول Filament في أي لوحة بياخده — بما فيها لوحات مش موجودة لسه.
     *    فيه لوحة واحدة دلوقتي (`AdminPanelProvider`)، والقرار ده متسجّل
     *    ومقبول. مفيش وسيط تنطيق مخصص بالقصد.
     *
     * ⚠️ **مكان النداء مهم.** `SupportServiceProvider` بيسجّل
     *    `$this->app->booted(fn () => ComponentManager::resolveScoped())`،
     *    و`boot()` بتاعنا بيشتغل **قبل** كولباكات `booted`. يعني التسجيل
     *    بيروح على الـ singleton الأساسي، والنسخة المستنسخة لكل طلب
     *    بتورثه. لو اتنقل لمكان بعد كده، هيتسجّل على نسخة الطلب ويضيع
     *    بين الطلبات — فيه اختبار بيثبت إنه بيعيش أكتر من طلب.
     *
     * ⚠️ حالة الجدول المحفوظة في الجلسة (فلاتر/ترتيب/بحث/أعمدة) مفتاحها
     *    `md5(class)` بس — **مش** فيه مستأجر. الجلسة لكل مستخدم فمفيش
     *    تسريب، لكن مستخدم في مستأجرين هيلاقي فلاتره منتقلة معاه لما
     *    يبدّل. قيد تجربة استخدام معروف ومقبول، مش مسألة أمنية.
     */
    private function configureTableDefaults(): void
    {
        Table::configureUsing(static function (Table $table): void {
            $table
                ->defaultPaginationPageOption(25)
                ->paginated([10, 25, 50, 100])
                ->extremePaginationLinks()
                ->persistFiltersInSession()
                ->persistSortInSession()
                ->persistSearchInSession()
                ->persistColumnSearchesInSession()
                ->persistColumnsInSession()
                // الصفحة بتظهر فوراً والجدول بيتحمّل بعدها
                ->deferLoading()
                // الفلاتر متتطبّقش لحد ما يضغط «تطبيق»
                ->deferFilters()
                // بحث لما يسيب الحقل مش مع كل حرف
                ->searchOnBlur()
                ->striped()
                ->reorderableColumns()
                ->emptyStateHeading(__('table.empty.heading'))
                ->emptyStateDescription(__('table.empty.description'))
                ->emptyStateIcon(Heroicon::OutlinedInbox);
        });
    }

    /**
     * سياق المستأجر عمره ما يعدّي من job للي بعدها.
     *
     * `TenantContext` singleton بحالة قابلة للتغيير، و`queue:work` بيقلّع
     * الـ container مرة واحدة وبيعيد استخدامه لكل الـ jobs. من غير المسح ده،
     * job نسيت تضبط سياقها بتشتغل على مستأجر الـ job اللي فاتت — **من غير
     * أي استثناء**، لأن `TenantScope` بيرمي على السياق الفاضي بس، مش على
     * السياق البايت.
     *
     * ده بالظبط التسريب اللي `docs/20` مصنّفه الخطر رقم ١، والقاعدة مكتوبة في
     * `CLAUDE.md`: «الطوابير مابتحملش سياق مستأجر — مرّر tenantId صراحةً».
     * السطر ده بيخلّي القاعدة دي **مفروضة** مش متمنّية.
     */
    private function forgetTenantContextBetweenJobs(): void
    {
        Queue::before(static function (): void {
            app(TenantContextContract::class)->forget();
        });
    }

    /**
     * بيحقن المستأجر وطلب الـ request_id والـ ip في `properties` بتاع كل
     * سطر نشاط، قبل ما يتحفظ. (docs/11 بند ٤)
     *
     * ⚠️ `tapActivity()` **مش موجودة** في spatie/laravel-activitylog ^5.1 —
     *    اتفحص السورس المُثبَّت وطلعت صفر نتيجة. البديل الحقيقي المتحقّق منه
     *    هو `LogActivityAction::beforeLogging()`، بينده قبل كل `Activity::save()`
     *    بغض النظر عن الموديل اللي بيسجّل.
     *
     * ⚠️ `clearBeforeLoggingCallbacks()` قبل التسجيل إلزامي: المصفوفة
     *    `static`، ومش محدودة بالحاوية — فكل تمهيد جديد لتطبيق (كل اختبار
     *    Pest) كان هيضيف كولباك تاني فوق اللي فاتوا من غير المسح ده، والمستأجر
     *    كان هيتسجّل مرات مكررة في `properties`.
     *
     * ⚠️ نفس مصدر السياق بتاع اللوج البنيوي بالظبط — مفيش آلية تانية:
     *    `TenantContext` (عبر `ActivityLogContext`)، و`Log::sharedContext()`
     *    لـ request_id/ip. مفيش `request()->ip()` هنا ولا في الموديل.
     *
     * ⚠️ التنقية هنا كمان — نفس `config('logging.redact')` بتاع اللوج
     *    البنيوي، عبر `Redactor` المشتركة. مفيش قايمة تانية مكررة. بتتطبّق
     *    على `properties` و`attribute_changes` الاتنين، لأن التغييرات
     *    القديمة/الجديدة بتتخزن في `attribute_changes` مش `properties`.
     *
     * ⚠️ الحذف/الاسترجاع بيتحوّل لـ `log_name = security` بغض النظر عن
     *    اسم اللوج اللي الموديل مسجّله (`useLogName('identity')` مثلاً) —
     *    حذف سجل عملية حساسة زي تصعيد الصلاحيات، ولازم يفضل بعد التقليم
     *    الدوري. `LogOptions::useLogName()` مابتاخدش closure فبقيمة ثابتة
     *    واحدة بس على مستوى الموديل، فالتعديل حسب الحدث لازم يحصل هنا.
     *    (docs/11 بند ٥)
     */
    private function enrichActivityLog(): void
    {
        LogActivityAction::clearBeforeLoggingCallbacks();

        LogActivityAction::beforeLogging(static function (ActivityContract $activity): void {
            if (! $activity instanceof Activity) {
                return;
            }

            $redactor = app(Redactor::class);
            $stamp = app(ActivityLogContext::class)->stamp();

            $activity->properties = collect($redactor->redact(
                collect($activity->properties ?? [])->merge($stamp)->toArray(),
            ));

            if ($activity->attribute_changes !== null) {
                $activity->attribute_changes = collect(
                    $redactor->redact(collect($activity->attribute_changes)->toArray()),
                );
            }

            if (in_array($activity->event, ['deleted', 'restored'], true)) {
                $activity->log_name = 'security';
            }
        });
    }

    /**
     * سياسة كلمات المرور الافتراضية — عالمية على أي `Password::default()`،
     * بما فيها حقل كلمة المرور بتاع Filament نفسه في `EditProfile`. (docs/12 بند ٤)
     *
     * ⚠️ `uncompromised()` بس في الإنتاج — بتعمل نداء HTTP لواجهة Have I
     *    Been Pwned، وده مش مناسب في الاختبارات ولا التطوير المحلي.
     */
    private function configurePasswordDefaults(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min((int) config('security.password.min_length', 12))
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();

            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });
    }

    /**
     * فحوصات صحة النظام. (docs/11 بند ٧)
     *
     * ⚠️ `HorizonCheck` من كود الوثيقة **لسه مؤجّلة بالقصد**: مش جزء من
     *    الشريحة دي (docs/11 بند ٩ بس)، وتسجيلها دلوقتي برّه نطاق الشريحة.
     *
     * ⚠️ `BackupsCheck` بقت مسجّلة فعلاً بعد تثبيت `spatie/laravel-backup`
     *    (Week 5 Slice 5.6، docs/11 بند ٩) — `onDisk('s3-private')` نفس
     *    ديسك `config('backup.backup.destination.disks')`، و`locatedAt()`
     *    بمجلد التطبيق (`config('backup.backup.name')`) عشان الفحص يعدّ
     *    ملفات النسخ الاحتياطي بس، مش كل حاجة تانية على نفس الديسك الخاص.
     *
     * ⚠️ `ScheduleCheck` محتاجة نبضة دورية عشان تثبت إن الجدولة شغّالة —
     *    مسجّلة في `routes/console.php` (`health:schedule-check-heartbeat`
     *    كل دقيقة)، بنفس منطق `activitylog:prune` الموجودة أصلاً.
     */
    private function configureHealthChecks(): void
    {
        Health::checks([
            DatabaseCheck::new(),
            RedisCheck::new(),
            CacheCheck::new(),
            QueueCheck::new()->onQueue(['default', config('notifications.queue')]),
            UsedDiskSpaceCheck::new()
                ->warnWhenUsedSpaceIsAbovePercentage(70)
                ->failWhenUsedSpaceIsAbovePercentage(85),
            ScheduleCheck::new(),
            DatabaseConnectionCountCheck::new()->warnWhenMoreConnectionsThan(50),
            // عدد سطور failed_jobs المتراكمة — docs/13 بند ٥
            FailedJobsCountCheck::new()->failWhenFailedJobsCountIsAbove(50),
            // النسخ الاحتياطي — docs/11 بند ٩
            BackupsCheck::new()
                ->onDisk('s3-private')
                ->locatedAt((string) config('backup.backup.name')),
            OptimizedAppCheck::new(),
            DebugModeCheck::new(),
            EnvironmentCheck::new(),
        ]);
    }

    /**
     * وسم queue على سياق Sentry وقت فشل أي job. (docs/13 بند ٥)
     *
     * ⚠️ منطق الوسم نفسه في `TagFailedJobForSentry` — كلاس مستقل مش
     *    closure هنا، عشان يتقدر يتفحص لوحده من غير `event()` (اللي هيشغّل
     *    listeners Horizon الداخلية المسجّلة على نفس الحدث كمان).
     */
    private function tagFailedJobsForSentry(): void
    {
        Queue::failing([TagFailedJobForSentry::class, 'handle']);
    }

    /**
     * قيمة البروكسيات الموثوقة خلف اللوحة. (docs/14 بند ١)
     *
     * ⚠️ `TrustProxies::at()` مباشرة مش `$middleware->trustProxies(at: ...)`
     *    في `bootstrap/app.php`: الكلوجر هناك بيتنفّذ وقت بناء الـ
     *    Application نفسها، قبل ما config يبقى متاح — `config()` هناك
     *    بيرمي BindingResolutionException فعلياً (اتحقق). هنا في `boot()`
     *    بعد ما الإقلاع يكتمل، `config()` آمن — نفس مكان
     *    `configurePasswordDefaults()`. مفيش env() برّه config/ (CLAUDE.md
     *    بند ٨) — القيمة الحقيقية في config/app.php ('trusted_proxies').
     *    الـ headers بتضبط في bootstrap/app.php نفسه لأنها ثابتة مش بيئية.
     */
    private function configureTrustedProxies(): void
    {
        TrustProxies::at((string) config('app.trusted_proxies'));
    }

    /**
     * فرض https على كل رابط بيتولّد وقت الإنتاج بس. (docs/14 بند ١)
     *
     * ⚠️ منطق الفرض نفسه في `ForceHttpsInProduction` — كلاس مستقل مش
     *    سطرين هنا، عشان يتقدر يتفحص لوحده من غير ما نعيد نداء `boot()`
     *    بالكامل (اللي بيفشل فعلياً لأن `configureHealthChecks()` بتتراكم).
     *
     * ⚠️ لازم تكون بعد `trustProxies()` (`bootstrap/app.php`) في ترتيب
     *    التنفيذ — من غيرها الطلب الحقيقي وراء بروكسي منهي TLS بيتشاف
     *    كـ HTTP أصلاً، فالفرض هنا مجرد شبكة أمان إضافية مش الإصلاح
     *    الوحيد. `trustProxies()` بيشتغل كل طلب (middleware)، وده بيتنفّذ
     *    مرة واحدة وقت الإقلاع — الاتنين لازمين مع بعض.
     */
    private function forceHttpsInProduction(): void
    {
        (new ForceHttpsInProduction)->handle($this->app);
    }
}
