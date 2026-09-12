<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Authorization;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Src\Support\Application\Contracts\PanelAccess as PanelAccessContract;
use Src\Support\Application\Contracts\TenantContext;

/**
 * فحص الدخول للوحة عبر مستأجري المستخدم. (ADR-026)
 *
 * Filament بينادي canAccessPanel **قبل** اختيار المستأجر، وإسنادات الأدوار
 * مخصّصة بمستأجر (`model_has_roles.tenant_id` معرّف NOT NULL). يعني السؤال
 * بصيغة «في المستأجر الحالي؟» مالوش إجابة وقت الدخول — الكلاس ده بيحوّله
 * لـ «في أي مستأجر؟».
 *
 * 📍 مكانه `Infrastructure\Authorization\` حسب ADR-006.
 */
final class PanelAccess implements PanelAccessContract
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly Gate $gate,
    ) {}

    public function allowsInAnyTenant(Authenticatable $user, string $ability, array $tenantIds): bool
    {
        if ($tenantIds === []) {
            return false;
        }

        // الفحص بيلف على المستأجرين وبيغيّر السياق مع كل واحد، فلازم يرجّعه
        // زي ما كان. من غير ده باقي الطلب بيشتغل بسياق آخر مستأجر اتفحص —
        // تسريب صامت بين المستأجرين، وهو أخطر بند في docs/20.
        //
        // ⚠️ الحالة الوحيدة اللي مش بتترجّع بالظبط هي set(null) الصريحة:
        //    بترجع كـ forget() لأن العقد مابيعرّضش «اتضبط ولا لأ». الفرق ده
        //    نظري هنا — الدالة بتتنادى وقت الدخول، قبل أي سياق. (ADR-008)
        $previous = $this->tenants->id();

        try {
            foreach ($tenantIds as $tenantId) {
                // set() بيضبط team بتاع spatie وبيمسح كاش الصلاحيات —
                // فمش محتاجين نلمس PermissionRegistrar من هنا.
                $this->tenants->set($tenantId);
                $this->forgetLoadedGrants($user);

                if ($this->gate->forUser($user)->allows($ability)) {
                    return true;
                }
            }

            return false;
        } finally {
            $previous === null
                ? $this->tenants->forget()
                : $this->tenants->set($previous);

            $this->forgetLoadedGrants($user);
        }
    }

    /**
     * من غير ده، علاقات الأدوار والصلاحيات المحمّلة من أول مستأجر بتفضل
     * في الذاكرة، وكل مستأجر بعده بيتفحص بإسنادات مستأجر تاني.
     */
    private function forgetLoadedGrants(Authenticatable $user): void
    {
        if ($user instanceof Model) {
            $user->unsetRelation('roles');
            $user->unsetRelation('permissions');
        }
    }
}
