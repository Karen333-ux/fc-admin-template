<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Sentry\Event;
use Sentry\State\Scope;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Support\Domain\Models\Tenant;
use Src\Support\Presentation\Http\Middleware\EnrichSentryScope;
use Symfony\Component\HttpFoundation\Response;

/**
 * إثراء سياق Sentry بالمستأجر والمستخدم. (docs/11 بند ٦)
 */
it('الميدلوير بيحقن tenant_id والمستخدم في سياق Sentry', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->tenants()->attach($tenant);

    Auth::login($user);
    Filament::setTenant($tenant);
    actingWithinTenant($tenant);

    (new EnrichSentryScope)->handle(
        Request::create('/'),
        fn (): Response => new Response,
    );

    $event = Event::createEvent();

    \Sentry\configureScope(function (Scope $scope) use ($event): void {
        $scope->applyToEvent($event);
    });

    expect($event->getTags())->toHaveKey('tenant_id', (string) $tenant->id)
        ->and($event->getUser()?->getId())->toBe($user->id);
});

it('config send_default_pii معطّلة دايماً — مانبعتش بيانات شخصية', function (): void {
    expect(config('sentry.send_default_pii'))->toBeFalse();
});

it('نسب العينة الافتراضية زي docs/11 بند ٦ بالظبط', function (): void {
    expect(config('sentry.traces_sample_rate'))->toBe(0.2)
        ->and(config('sentry.profiles_sample_rate'))->toBe(0.1);
});

it('الميدلوير متسجّل على مصادقة اللوحة', function (): void {
    expect(Filament::getPanel('admin')->getAuthMiddleware())->toContain(EnrichSentryScope::class);
});
