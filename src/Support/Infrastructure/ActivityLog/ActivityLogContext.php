<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\ActivityLog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Log;
use Src\Support\Application\Contracts\TenantContext;

/**
 * حدّ المستأجر على سجل النشاط (spatie/laravel-activitylog). (docs/11 بند ٤)
 *
 * جدول `activity_log` عقد إطار ومافيهوش `tenant_id` — بالظبط زي جدول
 * `media` و`notifications`. فالملكية بتتسجّل جوّه `properties` وقت الكتابة،
 * والفلترة بتحصل هنا. نفس منطق `MediaOwnership`/`NotificationOwnership`.
 *
 * ⚠️ `TenantBoundary::crosses()` بيتحقّق من `BelongsToTenant` بس — و`Activity`
 *    (زي `Media`/`Notification` قبله) مش بتستخدمه. يعني الحارس التلقائي في
 *    `Policy::decideFor()` وتأكيد `Gate::before` من عبور المستأجر **الاتنين
 *    بيرجعوا `null`/`false` عليه بصمت**. القرار هنا زي سابقيه بالظبط: مفيش
 *    Policy على سجل واحد لـ Activity — بس Gates على الكتالوج (من غير موديل)،
 *    والعزل الفعلي بيحصل هنا وبس، صراحةً في كل استعلام/عرض. (ADR-022 · ADR-024)
 *
 * القاعدة (زي `NotificationOwnership` بالظبط):
 *   • نشاط عليه `tenant_id` في `properties` → بيظهر جوّه المستأجر ده بس
 *   • نشاط من غير `tenant_id` (مثلاً محاولة دخول فاشلة — قبل ما المستأجر
 *     يتحدّد أصلاً) → حدث على مستوى الحساب، بيظهر في كل مستأجرات المستخدم
 *
 * التاني مش ثغرة: محاولة دخول فاشلة بتخص الحساب نفسه، ولازم كل مستأجرات
 * المستخدم تشوفها — بالظبط زي إشعار «اتغيّرت كلمة مرورك».
 */
final readonly class ActivityLogContext
{
    public const TENANT_KEY = 'tenant_id';

    public const REQUEST_KEY = 'request_id';

    public const IP_KEY = 'ip';

    public function __construct(private TenantContext $context) {}

    public function currentTenantId(): ?int
    {
        return $this->context->id();
    }

    /**
     * الخصائص اللي بتتحقن في `properties` بتاع كل سطر نشاط.
     *
     * @return array<string, int|string|null>
     */
    public function stamp(): array
    {
        return [
            self::TENANT_KEY => $this->context->id(),
            self::REQUEST_KEY => Log::sharedContext()['request_id'] ?? null,
            self::IP_KEY => Log::sharedContext()['ip'] ?? null,
        ];
    }

    /**
     * بيقيّد استعلام سجل النشاط على المستأجر الحالي.
     *
     * @template TBuilder of Builder|Relation
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public function scope(Builder|Relation $query): Builder|Relation
    {
        $tenantId = $this->context->id();

        return $query->where(function ($inner) use ($tenantId): void {
            $inner->whereNull('properties->'.self::TENANT_KEY);

            if ($tenantId !== null) {
                $inner->orWhere('properties->'.self::TENANT_KEY, $tenantId);
            }
        });
    }

    /**
     * هل سطر النشاط ده يخص المستأجر الحالي؟ (للفحص على سجل واحد)
     *
     * @param  array<string, mixed>  $properties
     */
    public function belongsToCurrentTenant(array $properties): bool
    {
        $owner = $properties[self::TENANT_KEY] ?? null;

        if ($owner === null) {
            return true;
        }

        $current = $this->context->id();

        return $current !== null && (int) $owner === $current;
    }
}
