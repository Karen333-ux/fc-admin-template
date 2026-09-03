<?php

declare(strict_types=1);

namespace Src\Support\Presentation\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Src\Support\Application\Contracts\LocaleDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * بيضبط لغة الطلب. (docs/10 بند ٤)
 *
 * ترتيب الحسم — الأخص بيغلب:
 *   ١. لغة المستخدم المحفوظة (`users.locale`)
 *   ٢. اللغة في الجلسة (لضيف بدّل اللغة قبل ما يسجّل دخول)
 *   ٣. اللغة الافتراضية للتثبيت (`GeneralSettings::default_locale`)
 *
 * ⚠️ الاحتياطي بيعدّي على عقد `LocaleDefaults` مش على `GeneralSettings`
 *    مباشرةً: الميدلوير ده في `Support`، و`Support` ممنوع يستورد أي سياق
 *    (ADR-011). نفس نمط ADR-022.
 *
 * ⚠️ لغة مش في `supported_locales` بتتجاهل بدل ما ترمي: القيمة ممكن تكون
 *    من صف قديم في قاعدة البيانات بعد ما لغة اتشالت من الكونفيج، ومايصحّش
 *    ده يقفل اللوحة في وش المستخدم.
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        app()->setLocale($locale);
        Carbon::setLocale($locale);
        CarbonImmutable::setLocale($locale);

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $candidates = [
            Auth::user()?->getAttribute('locale'),
            $request->hasSession() ? $request->session()->get('locale') : null,
            app(LocaleDefaults::class)->default(),
        ];

        /** @var list<string> $supported */
        $supported = config('app.supported_locales', []);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && in_array($candidate, $supported, true)) {
                return $candidate;
            }
        }

        return (string) config('app.fallback_locale');
    }
}
