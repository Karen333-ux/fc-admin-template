<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Src\Support\Presentation\Http\Middleware\AssignRequestContext;
use Src\Support\Presentation\Http\Middleware\SecurityHeaders;
use Src\Support\Presentation\Http\Middleware\SetLocale;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // بروكسي عكسي/load balancer بينهي TLS — من غيرها كل رابط مولّد
        // بيبقى http:// حتى لو الطلب الحقيقي جاي على https. (docs/14 بند ١)
        //
        // ⚠️ بند الـ headers بس هنا — مش env() ولا config(): الكلوجر ده
        //    بيتنفّذ وقت بناء الـ Application نفسها، **قبل** ما
        //    LoadConfiguration bootstrapper يشتغل. `config('app.trusted_proxies')`
        //    هنا بيرمي BindingResolutionException ("Target class [config]
        //    does not exist") — اتحقق فعلياً وقت التنفيذ، مش افتراض.
        //    الـ headers bitmask ثابت مش بيئي، فآمن يفضل هنا.
        //
        // ⚠️ قيمة الـ proxies (`at:`) — اللي فعلاً محتاجة القيمة البيئية —
        //    متسجّلة من `AppServiceProvider::configureTrustedProxies()` عبر
        //    `TrustProxies::at()` مباشرة بدل كده، لأن `boot()` بيتنفّذ
        //    **بعد** ما config يبقى متاح — نفس مكان `configurePasswordDefaults()`
        //    اللي بتقرا config() بأمان فعلاً. مفيش env() برّه config/
        //    (CLAUDE.md بند ٨) — القيمة الحقيقية في config/app.php
        //    ('trusted_proxies').
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // ⚠️ ده بيأثّر بس على routes/web.php (حالياً redirect الجذر بس) —
        //    مش على راوتات لوحة Filament. اللوحة بتسجّل نسخها الخاصة من
        //    نفس التلاتة على ->middleware() في AdminPanelProvider، لأن
        //    Filament بيبني middleware stack مستقل مش عبر مجموعة 'web'
        //    (نفس السبب الموثّق في SecurityHeaders::class docblock). فمفيش
        //    تكرار للرؤوس/السياق على طلبات اللوحة، والإضافة هنا بس
        //    تغطية أي راوت مستقبلي تحت 'web' برّه اللوحة. (docs/14 بند ١)
        $middleware->web(append: [
            SetLocale::class,
            AssignRequestContext::class,
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
