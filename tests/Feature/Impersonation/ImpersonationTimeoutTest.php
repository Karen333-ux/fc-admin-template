<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Presentation\Http\Middleware\EnforceImpersonationTimeout;
use Src\Support\Domain\Models\Tenant;
use STS\FilamentImpersonate\Facades\Impersonation;
use Symfony\Component\HttpFoundation\Response;

/**
 * المدة القصوى للانتحال — ٣٠ دقيقة، مفروضة سيرفرياً. (docs/12 بند ٣ قاعدة ٥)
 */
afterEach(function (): void {
    Impersonation::clear();
    Carbon::setTestNow();
});

function runTimeoutMiddleware(): void
{
    (new EnforceImpersonationTimeout)->handle(
        request(),
        fn ($request) => new Response,
    );
}

it('الانتحال لسه شغّال قبل ٣٠ دقيقة', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    Impersonation::enter($admin, $target);

    Carbon::setTestNow(now()->addMinutes(29));

    runTimeoutMiddleware();

    expect(Impersonation::isImpersonating())->toBeTrue()
        ->and(Auth::id())->toBe($target->getKey());
});

it('الانتحال بينتهي عند ٣٠ دقيقة بالظبط', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    Impersonation::enter($admin, $target);

    Carbon::setTestNow(now()->addMinutes(30)->addSecond());

    runTimeoutMiddleware();

    expect(Impersonation::isImpersonating())->toBeFalse();
});

it('انتهاء المهلة بيرجّع المستخدم الأصلي', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    Impersonation::enter($admin, $target);

    Carbon::setTestNow(now()->addMinutes(31));

    runTimeoutMiddleware();

    expect(Auth::id())->toBe($admin->getKey());
});

it('مستأجر الأصل بيرجّع صح — سياق المستأجر بيتصفّر مع الانتهاء', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    actingWithinTenant($tenant);
    Impersonation::enter($admin, $target);

    Carbon::setTestNow(now()->addMinutes(31));

    runTimeoutMiddleware();

    // الجلسة الأصلية اتقفلت — أي وصول لبيانات مستأجر تاني بعد كده لازم
    // يعدّي على InitializeTenantContext تاني من الصفر في الطلب اللي بعده
    expect(Impersonation::isImpersonating())->toBeFalse()
        ->and(Auth::id())->toBe($admin->getKey());
});

it('النشاط العادي (وسيط الـ 2FA مثلاً) مابيمدّدش المهلة', function (): void {
    // ⚠️ الميدلوير بتقرا الطابع الزمني بس — مابتحدّثوش أبداً
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    Impersonation::enter($admin, $target);

    Carbon::setTestNow(now()->addMinutes(20));
    runTimeoutMiddleware();
    expect(Impersonation::isImpersonating())->toBeTrue();

    Carbon::setTestNow(now()->addMinutes(20)); // مجموع ٤٠ من البداية
    runTimeoutMiddleware();

    expect(Impersonation::isImpersonating())->toBeFalse();
});

it('انتهاء المهلة بيسجّل نهاية الانتحال في activity', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    Impersonation::enter($admin, $target);

    Carbon::setTestNow(now()->addMinutes(31));

    runTimeoutMiddleware();

    $activity = Activity::query()
        ->where('event', 'impersonation_stopped')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull();
});
