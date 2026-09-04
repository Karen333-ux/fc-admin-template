<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Infrastructure\Listeners;

use function activity;

use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\RateLimiter;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Infrastructure\Notifications\RepeatedFailedLoginAttemptsNotification;

/**
 * قفل مؤقت + إشعار بريدي بعد محاولات دخول فاشلة متكررة على نفس الحساب.
 * (docs/12 بند ٥)
 *
 * ⚠️ «القفل المؤقت» الفعلي على الدخول وتحدي 2FA بيحصل جوه Filament نفسها
 *    (`WithRateLimiting`/`isMultiFactorChallengeRateLimited()` — اتفحص
 *    السورس المُثبَّت، تفاصيل القرار في `config/security.php`). المستمع ده
 *    بيقفّل الجزء الناقص بس: عدّ المحاولات الفاشلة الحقيقية **على مستوى
 *    الحساب** (`RateLimiter` بمفتاح منفصل، عشان القفل الفعلي أصلاً IP-based
 *    مش حساب-based)، وبعت إشعار بريد مرة واحدة بس لما العدّاد يوصل للحد،
 *    وتسجيل الحدث تحت `activity('security')` — نفس منطق
 *    `LogFailedLoginActivity` المجاور.
 *
 * ⚠️ لو الإيميل مش موجود أصلاً `$event->user` بيبقى `null` — مفيش حساب
 *    نبعتله إشعار، ومفيش عدّاد نزوده. نفس حارس `LogFailedLoginActivity`.
 */
final class NotifyOnRepeatedFailedLoginAttempts
{
    public function handle(Failed $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $threshold = (int) config('security.login.lockout_threshold', 5);
        $decaySeconds = (int) config('security.login.lockout_decay_minutes', 1) * 60;
        $key = 'login-lockout-notify:'.$event->user->getKey();

        RateLimiter::hit($key, $decaySeconds);

        if (RateLimiter::attempts($key) !== $threshold) {
            return;
        }

        activity('security')
            ->performedOn($event->user)
            ->causedBy(null)
            ->event('login_lockout')
            ->log(__('audit.events.auth.login_lockout'));

        $event->user->notify(new RepeatedFailedLoginAttemptsNotification(
            request()->ip() ?? '0.0.0.0',
        ));
    }
}
