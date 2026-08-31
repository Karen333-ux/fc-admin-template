<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Tenancy;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;
use Src\Support\Application\Contracts\TenantContext as TenantContextContract;
use Src\Support\Domain\Models\Tenant;
use Throwable;

/**
 * المستأجر الحالي من أي مكان.
 *
 * ⚠️ **لازم يتسجّل كـ singleton.** الكلاس فيه حالة قابلة للتغيير — لو اتسجّل bind
 * عادي، كل app(TenantContext::class) هترجّع نسخة جديدة سياقها فاضي، والعزل بيقع بصمت.
 */
final class TenantContext implements TenantContextContract
{
    private ?int $tenantId = null;

    /** الفرق بين «مامتضبطش» و«اتضبط بـ null» — ADR-008 */
    private bool $isSet = false;

    private bool $bypassed = false;

    public function id(): ?int
    {
        // اتضبط صراحةً؟ احترم القيمة حتى لو null.
        // من غير الفلاج ده، set(null) مابيمسحش السياق جوه طلب لوحة —
        // الـ ?? بترجع لمستأجر Filament، و«امسح السياق» بتبقى مستحيلة.
        if ($this->isSet) {
            return $this->tenantId;
        }

        return $this->tenantFromPanel();
    }

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->isSet = true;
        $this->syncDependents($tenantId);
    }

    /** ارجع لسلوك «خد المستأجر من Filament» */
    public function forget(): void
    {
        $this->tenantId = null;
        $this->isSet = false;
        $this->syncDependents(null);
    }

    public function isBypassed(): bool
    {
        return $this->bypassed;
    }

    /**
     * قراءة عبر كل المستأجرين — بشروط docs/20 بند ٣-٧:
     * أوامر console أو jobs بس، قراءة بس، نتيجة مجمّعة، وبتعليق يشرح ليه.
     */
    public function withoutScope(callable $callback): mixed
    {
        $previous = $this->bypassed;
        $this->bypassed = true;

        try {
            return $callback();
        } finally {
            $this->bypassed = $previous;
        }
    }

    public function forEachTenant(callable $callback): void
    {
        // ->get()->all() مش ->cursor(): الـ cursor بيقرا كسول، والـ bypass
        // بيتقفل في الـ finally قبل ما يتجاب صف واحد. لازم الصفوف تتقري
        // والـ bypass لسه مفتوح. (ADR-008)
        $tenants = $this->withoutScope(
            fn () => Tenant::query()->where('is_active', true)->get()->all(),
        );

        $hadContext = $this->isSet;
        $previous = $this->isSet ? $this->tenantId : null;

        try {
            foreach ($tenants as $tenant) {
                $this->set($tenant->getKey());
                $callback($tenant);
            }
        } finally {
            // رجّع السياق الأصلي — مش set(null) اللي بتسيب isSet = true
            $hadContext ? $this->set($previous) : $this->forget();
        }
    }

    /**
     * مستأجر اللوحة، لو فيه لوحة أصلاً.
     *
     * الـ Policy والـ Scope بيتنادوا من console و queue و test — مفيش لوحة
     * مبنية هناك، و Filament::getTenant() بترمي لو مفيش سياق لوحة.
     */
    private function tenantFromPanel(): ?int
    {
        try {
            $key = Filament::getTenant()?->getKey();
        } catch (Throwable) {
            return null;
        }

        return $key === null ? null : (int) $key;
    }

    private function syncDependents(?int $tenantId): void
    {
        // ١. spatie/permission teams — ده اللي بيعزل كاش الصلاحيات فعلياً
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenantId);
        $registrar->forgetCachedPermissions();

        // ٢. سياق اللوج
        Log::shareContext(['tenant_id' => $tenantId]);

        // ملاحظة: مفيش config(['cache.prefix' => ...]) هنا.
        // الـ store بيتبني مرة واحدة والبادئة بتتحط جواه وقت الإنشاء، فتغيير
        // الكونفيج بعد كده مالوش أي أثر. عزل كاش التطبيق بيتعمل بالـ tags
        // في مكان الاستدعاء. (ADR-008)
    }
}
