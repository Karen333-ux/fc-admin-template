<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Infrastructure\Listeners;

use function activity;

use Illuminate\Auth\Events\Failed;
use Illuminate\Database\Eloquent\Model;

/**
 * بيسجّل محاولات الدخول الفاشلة — أحد العمليات «اللي لازم تتسجّل دايماً»
 * في `docs/11` بند ٥ (كشف هجمات التخمين).
 *
 * ⚠️ `$event->credentials` معلّمة `#[SensitiveParameter]` في الإطار نفسه —
 *    بنسجّل الإيميل بس، مش المصفوفة كاملة، عشان كلمة المرور المُدخَلة
 *    (الغلط) متوصلش لسجل النشاط أصلاً. `Redactor` بتاعت `AppServiceProvider`
 *    شبكة أمان تانية، مش الاعتماد الوحيد.
 *
 * ⚠️ `$event->user` ممكن يكون `null` لو الإيميل مش موجود أصلاً — بنسجّل
 *    بدون `performedOn()` في الحالة دي، لأن مفيش سجل حقيقي نربطه بيه.
 */
final class LogFailedLoginActivity
{
    public function handle(Failed $event): void
    {
        $logger = activity('security')
            ->causedBy(null)
            ->event('login_failed')
            ->withProperties([
                'guard' => $event->guard,
                'email' => $event->credentials['email'] ?? null,
            ]);

        if ($event->user instanceof Model) {
            $logger->performedOn($event->user);
        }

        $logger->log(__('audit.events.auth.login_failed'));
    }
}
