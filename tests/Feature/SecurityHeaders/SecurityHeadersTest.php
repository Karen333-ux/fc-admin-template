<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Src\Support\Presentation\Http\Middleware\SecurityHeaders;
use Symfony\Component\HttpFoundation\Response;

/**
 * الرؤوس الأمنية الثابتة. (docs/12 بند ٦)
 */
it('الميدلوير متسجّل على اللوحة', function (): void {
    expect(Filament::getPanel('admin')->getMiddleware())->toContain(SecurityHeaders::class);
});

it('الرؤوس الأساسية موجودة على استجابة حقيقية من اللوحة', function (): void {
    $response = $this->get('/admin/login');

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
});

it('HSTS مش موجودة في بيئة الاختبار — مش إنتاج', function (): void {
    // ⚠️ نفس منطق اختبار uncompromised() في PasswordStrengthTest: مفيش
    // محاولة لتقليب `app()->environment()` وسط الاختبار، بنوثّق القرار
    // بس ونتأكد إن بيئة الاختبار فعلاً مش إنتاج.
    expect(app()->isProduction())->toBeFalse();

    $this->get('/admin/login')->assertHeaderMissing('Strict-Transport-Security');
});

it('الميدلوير بيضيف HSTS في بيئة الإنتاج', function (): void {
    // ⚠️ تعديل مباشر لـ 'env' بدل detectEnvironment(): الاختبارات بتشتغل
    // من الـ CLI، و detectEnvironment() بتحاول تقرا argv الأول، مش بس
    // تنادي الكلوجر. تعديل قيمة الحاوية مباشرة أدق وأبسط. التطبيق بيتعاد
    // إنشاؤه بالكامل قبل كل اختبار (refreshApplication)، فمفيش تسريب.
    app()['env'] = 'production';

    $middleware = new SecurityHeaders;
    $response = $middleware->handle(
        Request::create('/'),
        fn (): Response => new Response,
    );

    expect($response->headers->get('Strict-Transport-Security'))
        ->toBe('max-age=31536000; includeSubDomains; preload');
});

it('الميدلوير بيسيب استجابة الـ next زي ما هي غير الرؤوس', function (): void {
    $middleware = new SecurityHeaders;
    $response = $middleware->handle(
        Request::create('/'),
        fn (): Response => new Response('hello', 201),
    );

    expect($response->getStatusCode())->toBe(201)
        ->and($response->getContent())->toBe('hello');
});
