<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Presentation\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Src\Contexts\Identity\Domain\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * فرض المصادقة الثنائية على الأدوار المحددة، بعد فترة سماح. (docs/12 بند ١)
 *
 * ⚠️ بيستبدل `Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled`
 *    الافتراضية عبر `multiFactorAuthenticationRequiredMiddlewareName()` —
 *    الأصلية بتفرض 2FA على **كل** المستخدمين بلا استثناء ومن غير فترة سماح؛
 *    احتياجنا هنا مقيّد بأدوار محددة (`docs/12`: `super_admin`, `admin`) ومهلة.
 *
 * ⚠️ الفحص Gate على قدرة `require.two_factor`، **مش** فحص دور مباشر على
 *    المستخدم الفاعل — CLAUDE.md بيمنع فحص الدور المباشر (`has` + `Role`)
 *    على `$user` الفاعل بره الـ Policy. القدرة دي في كتالوج `config/authorization.php`
 *    زي `access.panel.admin` بالظبط، وممنوحة للأدوار المطلوبة هناك.
 *
 * ⚠️ الرجوع بيستخدم `Filament::getSetUpRequiredMultiFactorAuthenticationUrl()` —
 *    صفحة الإعداد **الأصلية** بتاعت Filament، مفيش صفحة موازية اتبنت.
 */
final class RequireTwoFactorAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if (! Gate::forUser($user)->allows('require.two_factor')) {
            return $next($request);
        }

        foreach (Filament::getMultiFactorAuthenticationProviders() as $provider) {
            if ($provider->isEnabled($user)) {
                return $next($request);
            }
        }

        if ($this->isWithinGracePeriod($user)) {
            return $next($request);
        }

        return redirect()->guest(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
    }

    private function isWithinGracePeriod(User $user): bool
    {
        $createdAt = $user->created_at;

        if ($createdAt === null) {
            return false;
        }

        $graceDays = (int) config('security.two_factor.grace_period_days', 0);

        return now()->lt($createdAt->copy()->addDays($graceDays));
    }
}
