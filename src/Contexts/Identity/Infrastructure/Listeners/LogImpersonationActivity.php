<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Infrastructure\Listeners;

use function activity;

use Illuminate\Database\Eloquent\Model;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Infrastructure\Authorization\ImpersonationContext;
use STS\FilamentImpersonate\Events\EnterImpersonation;
use STS\FilamentImpersonate\Events\LeaveImpersonation;

/**
 * بداية ونهاية الانتحال في `activity('security')` — الأثر الوحيد للوصول
 * العابر للمستأجرين. (docs/12 بند ٣ قاعدة ٤)
 *
 * ⚠️ بداية ونهاية الانتحال بتستخدم نفس مفتاح الخصائص `tenant_id` اللي
 *    `AppServiceProvider::enrichActivityLog()` (Slice 4.3) بيختمه تلقائياً
 *    من `TenantContext` الحالي وقت الحفظ — مش خاصية تانية باسم مختلف.
 *    الفكرة عمداً: بداية الانتحال بتحصل جوّه نفس الطلب اللي `TenantContext`
 *    فيه مظبوط صح على مستأجر الهدف بالفعل، فمفيش داعي لأي تدخل.
 *
 *    ⚠️ نهاية الانتحال (`filament-impersonate/leave`) بتمشي على middleware
 *    `web` بس — بره `InitializeTenantContext` — فـ `TenantContext` مش
 *    موثوق فيه وقتها. الحل: نضبطه يدوياً من `ImpersonationContext::targetTenantId()`
 *    (اتسجّل وقت البداية) **قبل** استدعاء `activity()`، عشان نفس الختم
 *    التلقائي يلتقط القيمة الصح — مش آلية تسجيل تانية، نفس الآلية بس
 *    بمدخل صحيح.
 */
final class LogImpersonationActivity
{
    public function handleEnter(EnterImpersonation $event): void
    {
        if (! $event->impersonated instanceof Model || ! $event->impersonator instanceof Model) {
            return;
        }

        $targetTenantId = app(TenantContext::class)->id();

        app(ImpersonationContext::class)->startWindow($targetTenantId);

        activity('security')
            ->performedOn($event->impersonated)
            ->causedBy($event->impersonator)
            ->event('impersonation_started')
            ->log(__('audit.events.user.impersonation_started'));
    }

    public function handleLeave(LeaveImpersonation $event): void
    {
        if (! $event->impersonator instanceof Model) {
            return;
        }

        $context = app(ImpersonationContext::class);

        app(TenantContext::class)->set($context->targetTenantId());

        $logger = activity('security')
            ->causedBy($event->impersonator)
            ->event('impersonation_stopped');

        if ($event->impersonated instanceof Model) {
            $logger->performedOn($event->impersonated);
        }

        $logger->log(__('audit.events.user.impersonation_stopped'));

        $context->clearWindow();
    }
}
