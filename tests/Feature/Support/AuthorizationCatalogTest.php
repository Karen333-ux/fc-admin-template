<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Src\Support\Infrastructure\Authorization\PermissionBuilder;

it('كل صلاحية في الكتالوج لها Gate معرّف', function (): void {
    // ADR-003 — من غير ده، access.panel.admin مالهاش Gate ومحدش بيفتح اللوحة
    $names = app(PermissionBuilder::class)->allPermissionNames();

    expect($names)->not->toBeEmpty();

    foreach ($names as $ability) {
        expect(Gate::has($ability))->toBeTrue("مفيش Gate للصلاحية {$ability}");
    }
});

it('كل صلاحية مترجمة ar و en', function (): void {
    $original = App::currentLocale();

    foreach (app(PermissionBuilder::class)->allPermissionNames() as $ability) {
        foreach (['ar', 'en'] as $locale) {
            App::setLocale($locale);

            expect(permission_label($ability))
                ->not->toContain('authorization.', "الصلاحية {$ability} مش مترجمة في {$locale}");
        }
    }

    App::setLocale($original);
});

it('*.users توسّع لكل أفعال users فقط', function (): void {
    // ADR-001 — الشق الشمال فعل واليمين مورد. users.* غلط.
    $expanded = app(PermissionBuilder::class)->expandPatterns(['*.users']);

    expect($expanded)->toHaveCount(12);       // ٩ افتراضية + ٣ extra

    foreach ($expanded as $name) {
        expect($name)->toEndWith('.users');
    }
});

it('access.* توسّع لصلاحيات الصفحات فقط', function (): void {
    $expanded = app(PermissionBuilder::class)->expandPatterns(['access.*']);

    expect($expanded)->toEqualCanonicalizing([
        'access.panel.admin',
        'access.dashboard',
    ]);
});
