<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Src\Support\Infrastructure\Authorization\InvariantRegistry;
use Src\Support\Infrastructure\Authorization\PermissionBuilder;
use Src\Support\Infrastructure\Authorization\TenantBoundary;

final class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerPolicyDiscovery();
        $this->registerCatalogGates();
        $this->registerSuperAdminBypass();
    }

    /**
     * الموديلات بتاعتنا مش في App\Models، فالاكتشاف التلقائي مش هيلاقيها.
     * (docs/19 بند ٦)
     */
    private function registerPolicyDiscovery(): void
    {
        Gate::guessPolicyNamesUsing(function (string $model): ?string {
            // موديلات بره بنيتنا (Spatie\Permission\Models\Role مثلاً) مالهاش Policy.
            // null معناها «مفيش Policy» — صريحة بدل ما تبقى صح بالصدفة.
            if (! str_contains($model, '\\Domain\\Models\\')) {
                return null;
            }

            return Str::of($model)
                ->replace('\\Domain\\Models\\', '\\Infrastructure\\Policies\\')
                ->append('Policy')
                ->toString();
        });
    }

    /**
     * كل صلاحية في الكتالوج ليها Gate. (ADR-003)
     *
     * ⚠️ من غير ده، `access.panel.admin` مالهاش Gate ومحدش بيفتح اللوحة.
     */
    private function registerCatalogGates(): void
    {
        $guard = config('authorization.guard');

        foreach (app(PermissionBuilder::class)->allPermissionNames() as $ability) {
            Gate::define($ability, static function (Authenticatable $user) use ($ability, $guard): Response {
                try {
                    // واحد من مكانين اتنين بس مسموح فيهم hasPermissionTo (CLAUDE.md)
                    $granted = (bool) $user->hasPermissionTo($ability, $guard);
                } catch (PermissionDoesNotExist) {
                    // صلاحية في الكونفيج ولسه ماتزامنتش → مرفوضة، مش خطأ
                    $granted = false;
                }

                // Response مش bool: القدرات اللي مالهاش موديل (صفحات الإعدادات مثلاً)
                // بتتفحص بالـ Gate دي مباشرةً، فـ bool كان بيضيّع سبب الرفض تماماً —
                // نفس العيب اللي docs/19 بند ٣ بيمنعه على الـ Policies. (ADR-019)
                return $granted
                    ? Response::allow()
                    : Response::deny(__('authorization.denied.missing_permission', [
                        'permission' => permission_label($ability),
                    ]));
            });
        }
    }

    /**
     * تجاوز المدير العام — بيحترم قواعد السلامة **وحدود المستأجر**.
     * (docs/19 بند ٥ · ADR-005)
     */
    private function registerSuperAdminBypass(): void
    {
        Gate::before(function (Authenticatable $user, string $ability, array $arguments = []): ?bool {
            if (! $user->hasRole(config('authorization.super_admin_role'))) {
                return null;   // ← null مش false، عشان الـ Policy تكمّل
            }

            $argument = $arguments[0] ?? null;

            // ١. قواعد السلامة — المدير العام مابيتخطاهاش.
            if (app(InvariantRegistry::class)->guards($ability, $argument)) {
                return null;
            }

            // ٢. حدود المستأجر — المدير العام مابيتخطاهاش كمان (ADR-005).
            //    null عشان الـ Policy تشتغل وترجّع 404 مش 403.
            if (app(TenantBoundary::class)->crosses($argument)) {
                return null;
            }

            return true;      // تجاوز الصلاحيات بس — جوه المستأجر الحالي
        });
    }
}
