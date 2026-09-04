<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Authorization;

use Illuminate\Support\Carbon;
use STS\FilamentImpersonate\Facades\Impersonation;

/**
 * هل الطلب الحالي جوّه جلسة انتحال؟ (docs/12 بند ٣)
 *
 * ⚠️ `isActive()` بتلف على `Impersonation::isImpersonating()` الحقيقية —
 *    اتفحص السورس المُثبَّت (`stechstudio/filament-impersonate` v5.6.0)،
 *    مش افتراض من الذاكرة. الحزمة بتدير جلسة الانتحال نفسها (تبديل
 *    الـ guard)؛ الكلاس ده بس بيضيف اللي الحزمة مابتوفّرهوش: تخزين
 *    مستأجر الهدف ووقت البداية عشان مدة الانتحال القصوى (بند ٥) وتسجيل
 *    النشاط (بند ٤) — الحزمة مالهاش أي مفهوم عن مدة قصوى ولا مستأجرات.
 *
 * ⚠️ مفاتيح الجلسة بادئة بـ `impersonate.` عمداً — نفس بادئة مفاتيح
 *    الحزمة نفسها (`impersonate.back_to`, `impersonate.guard`)، عشان
 *    تفضل كلها جنب بعض ومعروفة إنها بتخص نفس الميزة.
 *
 * ⚠️ الكلاس في `Support\Infrastructure` — قدرة أمنية عابرة للسياقات
 *    (زي `TenantBoundary`/`InvariantRegistry` جنبه)، مش خاصة بـ Identity
 *    بس. مفيش استيراد من `Src\Contexts` هنا. (ADR-011)
 */
final readonly class ImpersonationContext
{
    private const string SESSION_TENANT_KEY = 'impersonate.target_tenant_id';

    private const string SESSION_STARTED_AT_KEY = 'impersonate.started_at';

    public function isActive(): bool
    {
        return Impersonation::isImpersonating();
    }

    /**
     * بداية نافذة الانتحال — بتتسجّل وقت الدخول، لما `TenantContext`
     * لسه بيعكس مستأجر الهدف صح (قبل أي إعادة توجيه). (docs/12 بند ٤ و٥)
     */
    public function startWindow(?int $targetTenantId): void
    {
        session()->put([
            self::SESSION_TENANT_KEY => $targetTenantId,
            self::SESSION_STARTED_AT_KEY => Carbon::now()->toIso8601String(),
        ]);
    }

    /**
     * ⚠️ مسار خروج الحزمة (`filament-impersonate/leave`) بيمشي على
     *    middleware `web` بس — مش تحت `InitializeTenantContext`. يعني
     *    `TenantContext` مش موثوق فيه هناك، والمفتاح ده هو المصدر الوحيد
     *    لمستأجر الهدف وقت تسجيل نشاط الخروج.
     */
    public function targetTenantId(): ?int
    {
        /** @var int|null $value */
        $value = session(self::SESSION_TENANT_KEY);

        return $value;
    }

    public function hasExpired(int $maxMinutes): bool
    {
        $startedAt = session(self::SESSION_STARTED_AT_KEY);

        if (! is_string($startedAt)) {
            return false;
        }

        return Carbon::parse($startedAt)->addMinutes($maxMinutes)->isPast();
    }

    /**
     * ⚠️ لازم تتنادى صراحةً بعد كل خروج (عادي أو بانتهاء المهلة) —
     *    `Impersonation::clear()` بتاعة الحزمة بتمسح مفاتيحها هي بس.
     */
    public function clearWindow(): void
    {
        session()->forget([self::SESSION_TENANT_KEY, self::SESSION_STARTED_AT_KEY]);
    }
}
