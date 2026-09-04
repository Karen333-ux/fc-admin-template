<?php

declare(strict_types=1);

use Filament\Pages\Dashboard;
use Illuminate\Support\Facades\Auth;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Support\Domain\Models\Tenant;
use STS\FilamentImpersonate\Facades\Impersonation;

/**
 * شريط التحذير — بيظهر أثناء الانتحال بس. (docs/12 بند ٣ قاعدة ٣)
 *
 * ⚠️ الشريط شغل الحزمة نفسها (`vendor/stechstudio/filament-impersonate`) —
 *    بتسجّل الـ render hook وتبني القالب لوحدها، مفيش View ولا تسجيل
 *    render hook من كودنا. الاختبارات هنا بتتأكد من **التكامل الفعلي**
 *    مش بتعيد اختبار الحزمة.
 */
afterEach(function (): void {
    Impersonation::clear();
});

it('الشريط بيظهر أثناء الانتحال', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    // الهدف لازم يقدر يفتح اللوحة أصلاً — access.panel.admin
    $target = userWithRole('viewer', $tenant);

    Impersonation::enter($admin, $target, 'web');

    $response = $this->get(Dashboard::getUrl(tenant: $tenant));

    $response->assertOk();
    $response->assertSee('impersonate-banner', false);
    $response->assertSee(route('filament-impersonate.leave'), false);
});

it('الشريط مش ظاهر في الوضع العادي', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    $response = $this->actingAs($admin)->get(Dashboard::getUrl(tenant: $tenant));

    $response->assertOk();
    $response->assertDontSee('impersonate-banner', false);
});

it('زرار المغادرة بيرجّع المستخدم الأصلي والشريط بيختفي بعدها', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    Impersonation::enter($admin, $target, 'web');

    $this->get(route('filament-impersonate.leave'))->assertRedirect();

    $response = $this->get(Dashboard::getUrl(tenant: $tenant));

    $response->assertDontSee('impersonate-banner', false);
    expect(Auth::id())->toBe($admin->getKey());
});
