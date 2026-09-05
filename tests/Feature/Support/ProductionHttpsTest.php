<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Kernel;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Src\Support\Infrastructure\Http\ForceHttpsInProduction;
use Src\Support\Presentation\Http\Middleware\AssignRequestContext;
use Src\Support\Presentation\Http\Middleware\SecurityHeaders;
use Src\Support\Presentation\Http\Middleware\SetLocale;

/**
 * TrustProxies + فرض HTTPS في الإنتاج. (docs/14 بند ١)
 */
it('trusted_proxies جايه من config/app.php مش env() مباشرة', function (): void {
    expect(config('app.trusted_proxies'))->toBe('*');
});

it('bootstrap/app.php مفيهوش نداء env() مباشر — CLAUDE.md بند ٨', function (): void {
    // stripPhpComments() من tests/Pest.php — من غيرها تعليقات الشرح
    // اللي بتوصف القرار ده نفسه (وفيها كلمة env() كنص) بتوقّع الفحص.
    $code = stripPhpComments((string) file_get_contents(base_path('bootstrap/app.php')));

    expect($code)->not->toContain('env(');
});

it('TrustProxies بتخلّي الطلب وراء بروكسي بـ X-Forwarded-Proto: https يتعتبر آمن', function (): void {
    // trusted_proxies الافتراضي '*' محلياً — بيثق بأي بروكسي مباشر،
    // فمفيش داعي لـ IP محدد هنا عشان الاختبار يعدّي.
    $request = Request::create('http://example.com/', 'GET', server: [
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ]);

    (new TrustProxies)->handle($request, fn (Request $req): Request => $req);

    expect($request->isSecure())->toBeTrue();
});

it('الميدلوير المطلوب متسجّل على مجموعة web', function (): void {
    $kernel = app(Kernel::class);

    expect($kernel->getMiddlewareGroups()['web'])->toContain(
        SetLocale::class,
        AssignRequestContext::class,
        SecurityHeaders::class,
    );
});

it('مفيش فرض https في بيئة الاختبار — مش إنتاج', function (): void {
    expect(app()->isProduction())->toBeFalse();
    expect(url('/'))->toStartWith('http://');
});

it('بيتفرض https على الروابط المولّدة في بيئة الإنتاج بس', function (): void {
    // ⚠️ نداء مباشر لـ ForceHttpsInProduction بدل إعادة boot() بالكامل —
    // نفس تعليق EnrichSentryScope/TagFailedJobForSentry: كلاس مستقل
    // معمول عشان الحالة دي بالظبط. إعادة نداء AppServiceProvider::boot()
    // كان هيفشل (Health::checks() بتتراكم مش تتستبدل).
    //
    // ⚠️ تعديل مباشر لـ 'env' بدل detectEnvironment() — نفس أسلوب
    // SecurityHeadersTest بالظبط.
    app()['env'] = 'production';

    (new ForceHttpsInProduction)->handle(app());

    expect(url('/'))->toStartWith('https://');
});

it('SESSION_SECURE_COOKIE مضبوطة محلياً وموصّلة لـ config/session.php', function (): void {
    // القيمة الفعلية null محلياً (مسار HTTP) — إجبارها true مكانه
    // .env الإنتاج بس، مش .env.example. (docs/14 بند ٢)
    expect(config('session.secure'))->toBeNull();

    $envExample = (string) file_get_contents(base_path('.env.example'));

    expect($envExample)->toContain('TRUSTED_PROXIES=')
        ->and($envExample)->toContain('SESSION_SECURE_COOKIE=');
});

it('مفاتيح AWS وSentry موثّقة في .env.example — مستهلكة فعلياً في config لكن كانت ناقصة', function (): void {
    // config/filesystems.php وconfig/sentry.php بيقروا المفاتيح دي فعلاً
    // (اتحقق وقت Slice 5.6) — القاعدة في docs/14 بند ٢: "أي مفتاح هنا
    // لازم يكون في .env.example بقيمة وهمية". مفيش تعديل على config
    // هنا، توثيق بس. (docs/14 بند ٢)
    $envExample = (string) file_get_contents(base_path('.env.example'));

    expect($envExample)->toContain('AWS_ACCESS_KEY_ID=')
        ->and($envExample)->toContain('AWS_SECRET_ACCESS_KEY=')
        ->and($envExample)->toContain('AWS_DEFAULT_REGION=')
        ->and($envExample)->toContain('AWS_BUCKET=')
        ->and($envExample)->toContain('AWS_PRIVATE_BUCKET=')
        ->and($envExample)->toContain('AWS_URL=')
        ->and($envExample)->toContain('SENTRY_LARAVEL_DSN=')
        ->and($envExample)->toContain('SENTRY_TRACES_SAMPLE_RATE=');
});
