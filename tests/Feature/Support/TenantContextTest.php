<?php

declare(strict_types=1);

use Src\Support\Application\Contracts\TenantContext as TenantContextContract;
use Src\Support\Domain\Models\Tenant;
use Src\Support\Infrastructure\Tenancy\TenantContext;

it('TenantContext مسجّل كـ singleton', function (): void {
    // اختبار تراجُع لـ ADR-015: التسجيل المقلوب كان بيعمل حلقة لا نهائية،
    // و`bind` عادي بيدّي نسخة فاضية كل مرة والعزل بيقع بصمت.
    expect(app(TenantContextContract::class))->toBe(app(TenantContextContract::class))
        ->and(app(TenantContext::class))->toBe(app(TenantContextContract::class));
});

it('forget بيرجّع لسلوك «خد المستأجر من Filament»', function (): void {
    // ADR-008 — forget() مش نفس set(null)
    $tenant = Tenant::factory()->create();
    $context = app(TenantContextContract::class);

    $context->set($tenant->getKey());
    expect($context->id())->toBe($tenant->getKey());

    $context->set(null);
    expect($context->id())->toBeNull();

    // من غير لوحة، الرجوع لـ Filament بيدّي null — بس عبر مسار مختلف
    $context->forget();
    expect($context->id())->toBeNull();
});

it('withoutScope بيفتح التجاوز ويقفله بعدها', function (): void {
    $context = app(TenantContextContract::class);

    expect($context->isBypassed())->toBeFalse();

    $inside = $context->withoutScope(fn (): bool => $context->isBypassed());

    expect($inside)->toBeTrue()
        ->and($context->isBypassed())->toBeFalse();
});

it('forEachTenant بيقرا الصفوف والـ bypass لسه مفتوح', function (): void {
    // ADR-008 — ->get()->all() مش ->cursor(): الـ cursor بيقرا كسول
    // والـ bypass بيتقفل قبل ما يتجاب صف واحد.
    Tenant::factory()->count(3)->create();
    Tenant::factory()->inactive()->create();

    $seen = [];

    app(TenantContextContract::class)->forEachTenant(function (Tenant $tenant) use (&$seen): void {
        $seen[] = $tenant->getKey();
    });

    expect($seen)->toHaveCount(3);   // النشطين بس
});

it('forEachTenant بيرجّع السياق الأصلي في النهاية', function (): void {
    $original = Tenant::factory()->create();
    Tenant::factory()->count(2)->create();

    $context = app(TenantContextContract::class);
    $context->set($original->getKey());

    $context->forEachTenant(fn (Tenant $tenant) => null);

    expect($context->id())->toBe($original->getKey());
});

it('forEachTenant بيرجّع لحالة «مش متضبّط» لو مكانش فيه سياق', function (): void {
    Tenant::factory()->count(2)->create();

    $context = app(TenantContextContract::class);
    $context->forget();

    $context->forEachTenant(fn (Tenant $tenant) => null);

    expect($context->id())->toBeNull();
});
