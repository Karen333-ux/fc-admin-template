<?php

declare(strict_types=1);

namespace Src\Support\Presentation\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Src\Support\Application\Contracts\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * ⚠️ **الأول في الترتيب** — كل ميدلوير بعده بيعتمد عليه.
 *
 * لو اشتغل بعد ميدلوير اللوج، كل سطر لوج في الطلب هيبقى tenant_id: null
 * وتتبّع أي مشكلة في الإنتاج بيبقى مستحيل. (docs/22 بند ٨)
 */
final class InitializeTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = Filament::getTenant()?->getKey();

        app(TenantContext::class)->set($key === null ? null : (int) $key);

        return $next($request);
    }
}
