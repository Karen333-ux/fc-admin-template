<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Authorization;

use Illuminate\Database\Eloquent\Model;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Infrastructure\Persistence\Concerns\BelongsToTenant;

/**
 * حد المستأجر — المدير العام مابيتخطاهوش. (ADR-005)
 *
 * 📍 مكانه `Infrastructure\Authorization\` حسب ADR-006 و docs/22 بند ١.
 *    (docs/19 بند ٥ بيكتب المسار غلط كـ `Infrastructure\Tenancy\` — سجل التناقض
 *     في تقرير الشريحة؛ سجل القرارات هو الحكم.)
 */
final class TenantBoundary
{
    /** هل السجل ده تابع لمستأجر غير المستأجر الحالي؟ */
    public function crosses(mixed $argument): bool
    {
        // اسم كلاس أو null — مفيش سجل نقارن بيه (viewAny / create)
        if (! $argument instanceof Model) {
            return false;
        }

        // موديل مش تابع لمستأجر (Tenant, User) — مالوش حد نعديه
        if (! in_array(BelongsToTenant::class, class_uses_recursive($argument), true)) {
            return false;
        }

        $current = app(TenantContext::class)->id();

        return $current !== null
            && $argument->getAttribute('tenant_id') !== $current;
    }
}
