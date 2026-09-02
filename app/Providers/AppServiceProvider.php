<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Src\Contexts\Settings\Infrastructure\Storage\SettingsStoragePreferences;
use Src\Support\Application\Contracts\DiskResolver;
use Src\Support\Application\Contracts\StoragePreferences;
use Src\Support\Application\Contracts\TenantContext as TenantContextContract;
use Src\Support\Infrastructure\Authorization\InvariantRegistry;
use Src\Support\Infrastructure\Authorization\PermissionBuilder;
use Src\Support\Infrastructure\Authorization\TenantBoundary;
use Src\Support\Infrastructure\Filesystem\MediaOwnership;
use Src\Support\Infrastructure\Filesystem\SettingsDrivenDiskResolver;
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

        // نظام الملفات: العقد في Support، والتنفيذ اللي بيقرا الإعدادات في
        // سياق Settings — الاتجاه ده بيحافظ على ADR-011. (ADR-022)
        $this->app->singleton(StoragePreferences::class, SettingsStoragePreferences::class);
        $this->app->singleton(DiskResolver::class, SettingsDrivenDiskResolver::class);
        $this->app->singleton(MediaOwnership::class);

        $this->app->singleton(InvariantRegistry::class);
        $this->app->singleton(TenantBoundary::class);
        $this->app->singleton(PermissionBuilder::class);
    }

    public function boot(): void
    {
        // ميجريشنز النواة المشتركة: tenants + tenant_user (ADR-011)
        $this->loadMigrationsFrom(
            base_path('src/Support/Infrastructure/Database/Migrations'),
        );

        $this->forgetTenantContextBetweenJobs();
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
}
