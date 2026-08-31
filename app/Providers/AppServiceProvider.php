<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Support\Application\Contracts\TenantContext as TenantContextContract;
use Src\Support\Infrastructure\Authorization\InvariantRegistry;
use Src\Support\Infrastructure\Authorization\PermissionBuilder;
use Src\Support\Infrastructure\Authorization\TenantBoundary;
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
    }
}
