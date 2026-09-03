<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Notifications;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Src\Support\Application\Contracts\TenantContext;

/**
 * حدّ المستأجر على الإشعارات. (ADR-024)
 *
 * جدول `notifications` عقد إطار ومافيهوش `tenant_id` — بالظبط زي جدول
 * `media` بتاع spatie. فالملكية بتتسجّل جوّه `data` وقت الإرسال، والفلترة
 * بتحصل هنا. نفس منطق `MediaOwnership`، بس الحد هنا **استعلام** مش تأكيد
 * على سجل واحد، لأن الجرس بيعرض قائمة.
 *
 * القاعدة:
 *   • إشعار عليه `tenant_id` → بيظهر جوّه المستأجر ده **بس**
 *   • إشعار من غير `tenant_id` → إشعار على مستوى المستخدم، بيظهر دايماً
 *
 * التاني مش ثغرة: «اتغيّرت كلمة مرورك» بتخص الحساب نفسه مش مؤسسة بعينها،
 * ولازم توصل في أي سياق.
 */
final readonly class NotificationOwnership
{
    public const TENANT_KEY = 'tenant_id';

    public function __construct(private TenantContext $context) {}

    /**
     * المستأجر الحالي — بيتقرا **وقت إنشاء الإشعار**، جوّه الطلب.
     *
     * ⚠️ لازم يتنادى في الكونستركتور مش في `toDatabase()`. كل إشعار عندنا
     *    `ShouldQueue`، و`AppServiceProvider::forgetTenantContextBetweenJobs()`
     *    بيمسح السياق في `Queue::before` — يعني جوّه الـ Job السياق **فاضي**
     *    دايماً. قراءة المستأجر هناك بتختم `null` وبتفتح الجرس على كل
     *    المستأجرين.
     *
     *    ده بالظبط اللي `CLAUDE.md` بيقوله: «الطوابير مابتحملش سياق مستأجر —
     *    مرّر tenantId صراحةً». (docs/13 · ADR-024)
     */
    public function currentTenantId(): ?int
    {
        return $this->context->id();
    }

    /**
     * الخصائص اللي بتتحقن في `data` بتاع الإشعار.
     *
     * بتاخد المستأجر صراحةً — مش بتقراه — عشان القيمة تكون بتاعت لحظة
     * الإنشاء مش لحظة تنفيذ الـ Job.
     *
     * @return array<string, int|null>
     */
    public function stamp(?int $tenantId): array
    {
        return [self::TENANT_KEY => $tenantId];
    }

    /**
     * بيقيّد استعلام الإشعارات على المستأجر الحالي.
     *
     * @template TBuilder of Builder|Relation
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public function scope(Builder|Relation $query): Builder|Relation
    {
        $tenantId = $this->context->id();

        $query->where(function ($inner) use ($tenantId): void {
            // إشعارات على مستوى المستخدم — من غير مستأجر
            $inner->whereNull('data->'.self::TENANT_KEY);

            if ($tenantId !== null) {
                $inner->orWhere('data->'.self::TENANT_KEY, $tenantId);
            }
        });

        return $query;
    }

    /**
     * هل الإشعار ده يخص المستأجر الحالي؟ (للفحص على سجل واحد)
     *
     * @param  array<string, mixed>  $data
     */
    public function belongsToCurrentTenant(array $data): bool
    {
        $owner = $data[self::TENANT_KEY] ?? null;

        if ($owner === null) {
            return true;
        }

        $current = $this->context->id();

        return $current !== null && (int) $owner === $current;
    }
}
