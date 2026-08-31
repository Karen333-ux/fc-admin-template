<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Authorization;

use Illuminate\Database\Eloquent\Model;

/**
 * الكلاس الأساسي لكل Policy. مكانه Infrastructure مش Domain. (ADR-006)
 */
abstract class Policy
{
    /** اسم المورد كما هو في config/authorization.php */
    abstract protected function resource(): string;

    /**
     * قدرات لا يتجاوزها المدير العام.
     *
     * دي مش صلاحيات — دي قواعد سلامة. مثال: «مينفعش تحذف نفسك»
     * لازم تفضل شغالة حتى للـ super_admin، وإلا هيقفل على نفسه.
     *
     * حدود المستأجر **مش** محتاجة تتسجّل هنا — بتتفرض تلقائياً
     * عبر decideFor() و Gate::before. (ADR-005 · ADR-007)
     *
     * @return list<string>
     */
    public function invariants(): array
    {
        return [];
    }

    public function isInvariant(string $ability): bool
    {
        return in_array($ability, $this->invariants(), true);
    }

    /** يبني اسم الصلاحية الكامل: view_any + users → view_any.users */
    public function permissionFor(string $action): string
    {
        return $action.config('authorization.separator').$this->resource();
    }

    /**
     * سلسلة فحص من غير سجل — للقدرات اللي مالهاش موديل.
     * استخدمها في viewAny() و create() بس.
     */
    protected function decide(): Decision
    {
        return new Decision;
    }

    /**
     * سلسلة فحص على سجل. **حارس المستأجر بيتطبّق هنا قبل أي حاجة تانية.**
     *
     * أي دالة بتاخد موديل كمعامل تاني لازم تستخدم دي — مش decide().
     * كده نسيان الحارس بقى مستحيل بدل ما يبقى مكشوف. (ADR-007)
     */
    protected function decideFor(Model $record): Decision
    {
        return (new Decision)->withinTenant($record);
    }
}
