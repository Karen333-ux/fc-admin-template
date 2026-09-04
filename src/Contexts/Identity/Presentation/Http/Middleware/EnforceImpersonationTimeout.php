<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Presentation\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Src\Support\Infrastructure\Authorization\ImpersonationContext;
use STS\FilamentImpersonate\Facades\Impersonation;
use Symfony\Component\HttpFoundation\Response;

/**
 * فرض المدة القصوى للانتحال — ٣٠ دقيقة، مش قابلة للتمديد. (docs/12 بند ٣ قاعدة ٥)
 *
 * ⚠️ فحص سيرفري على **كل** طلب مصادَق عليه، مش جافاسكريبت بس. الميدلوير
 *    بتقرا الطابع الزمني بس — مابتحدّثوش أبداً، فمفيش تمديد صامت بمجرد
 *    النشاط. الحزمة (`stechstudio/filament-impersonate`) مالهاش مفهوم
 *    «مدة قصوى» خالص (اتفحص السورس) — الإنفاذ ده كوده بالكامل، بس
 *    بيستخدم دورة حياة الحزمة الحقيقية (`Impersonation::leave()`) بدل
 *    ما يخترع آلية موازية.
 */
final class EnforceImpersonationTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(ImpersonationContext::class);

        if ($context->isActive() && $context->hasExpired((int) config('security.impersonation.max_minutes', 30))) {
            Impersonation::leave();

            return redirect()->guest(Filament::getUrl());
        }

        return $next($request);
    }
}
