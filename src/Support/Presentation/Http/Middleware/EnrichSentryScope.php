<?php

declare(strict_types=1);

namespace Src\Support\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sentry\State\Scope;
use Src\Support\Application\Contracts\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * بيحقن المستأجر والمستخدم في سياق Sentry لكل استجابة. (docs/11 بند ٦)
 *
 * ⚠️ `\Sentry\configureScope()` آمنة النداء حتى من غير DSN مضبوط — بترجع
 *    Hub خامل بيتجاهل القيم، مفيش داعي لفحص `app()->bound(...)` قبلها
 *    (اتفحص السورس المُثبَّت: `Sentry\functions.php`).
 *
 * ⚠️ في `authMiddleware` مش `middleware` العادية — لازم تشتغل بعد
 *    `tenantMiddleware`/`InitializeTenantContext` عشان `TenantContext`
 *    يكون اتضبط فعلاً، ونفس ترتيب `EnforceImpersonationTimeout` المجاورة.
 *
 * ⚠️ ID بس مش الإيميل — `send_default_pii => false` في `config/sentry.php`
 *    بتمنع بيانات شخصية إضافية أصلاً، وده تحقيق مزدوج لنفس القاعدة.
 */
final class EnrichSentryScope
{
    public function handle(Request $request, Closure $next): Response
    {
        \Sentry\configureScope(function (Scope $scope): void {
            $scope->setTag('tenant_id', (string) app(TenantContext::class)->id());
            $scope->setUser(['id' => auth()->id()]);
        });

        return $next($request);
    }
}
