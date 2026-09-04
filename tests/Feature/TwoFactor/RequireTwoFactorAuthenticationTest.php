<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Src\Support\Domain\Models\Tenant;

/**
 * فرض 2FA — مصفوفة الدور × المهلة × حالة التفعيل. (docs/12 بند ١)
 *
 * ⚠️ الميدلوير دي بتستبدل `EnsureMultiFactorAuthenticationIsEnabled`
 *    الافتراضية على **كل** مسارات اللوحة (`multiFactorAuthenticationRequiredMiddlewareName`)،
 *    فالاختبارات هنا بتضرب مسار حقيقي (Dashboard) عشان تتأكد من التوصيل
 *    الفعلي مش المنطق المعزول بس.
 */
it('دور مطلوب منه 2FA، لسه في فترة السماح، من غير 2FA — مسموح', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    // المستخدم اتعمل دلوقتي — created_at = الآن، فمهلة الأيام السبعة لسه فاتحة

    expect($this->actingAs($admin)->get(Dashboard::getUrl(tenant: $tenant))->getStatusCode())
        ->toBe(200);
});

it('دور مطلوب منه 2FA، فترة السماح خلصت، من غير 2FA — بيتوجّه لصفحة الإعداد', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $admin->forceFill(['created_at' => now()->subDays(30)])->saveQuietly();

    $response = $this->actingAs($admin)->get(Dashboard::getUrl(tenant: $tenant));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('multi-factor-authentication/set-up');
});

it('دور مطلوب منه 2FA، فترة السماح خلصت، لكن 2FA مفعّل — مسموح', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $admin->forceFill(['created_at' => now()->subDays(30)])->saveQuietly();
    $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    expect($this->actingAs($admin)->get(Dashboard::getUrl(tenant: $tenant))->getStatusCode())
        ->toBe(200);
});

it('دور مش مطلوب منه 2FA — مسموح حتى بعد فترة السماح', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = userWithRole('viewer', $tenant);
    $viewer->forceFill(['created_at' => now()->subDays(30)])->saveQuietly();

    expect($this->actingAs($viewer)->get(Dashboard::getUrl(tenant: $tenant))->getStatusCode())
        ->toBe(200);
});

it('صفحة الإعداد الإجباري نفسها مايتحصلش عليها ريدايركت لوب', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $admin->forceFill(['created_at' => now()->subDays(30)])->saveQuietly();

    $url = Filament::getSetUpRequiredMultiFactorAuthenticationUrl();

    expect($this->actingAs($admin)->get($url)->getStatusCode())->toBe(200);
});

it('صفحة الملف الشخصي متاحة — فيها إدارة 2FA الطوعية', function (): void {
    $tenant = Tenant::factory()->create();
    // دور مش مطلوب منه 2FA، عشان نتأكد إن صفحة البروفايل شغالة أصلاً
    $editor = userWithRole('editor', $tenant);

    expect($this->actingAs($editor)->get(Filament::getProfileUrl())->getStatusCode())
        ->toBe(200);
});
