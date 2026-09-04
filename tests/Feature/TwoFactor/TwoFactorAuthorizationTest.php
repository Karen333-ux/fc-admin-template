<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Src\Support\Domain\Models\Tenant;

/**
 * مصفوفة أدوار require.two_factor. (docs/12 بند ١)
 *
 * ⚠️ القدرة دي بس هي مصدر الحقيقة لمين لازم يفعّل 2FA — الميدلوير
 *    (`RequireTwoFactorAuthentication`) بيسألها عبر Gate، مش فحص دور مباشر.
 */
dataset('two_factor_matrix', [
    ['super_admin', true],
    ['admin', true],
    ['editor', false],
    ['viewer', false],
]);

it('مصفوفة أدوار require.two_factor', function (string $role, bool $required): void {
    $tenant = Tenant::factory()->create();
    $user = userWithRole($role, $tenant);

    actingWithinTenant($tenant);

    expect(Gate::forUser($user)->allows('require.two_factor'))
        ->toBe($required, "الدور {$role} على require.two_factor");
})->with('two_factor_matrix');

it('الرفض بيعدّي على Response برسالة مش bool', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = userWithRole('viewer', $tenant);

    actingWithinTenant($tenant);

    $response = app(Illuminate\Contracts\Auth\Access\Gate::class)
        ->forUser($viewer)
        ->inspect('require.two_factor');

    // ⚠️ مش بنتحقق إن الرسالة مترجمة بالكامل هنا — `permission_label()`
    //    فيها خلل سابق للشريحة دي بيأثر على أي مفتاح `pages` فيه نقطة
    //    (بما فيها `access.panel.admin` الموجودة من أسبوع ١، اتفحص بـ
    //    tinker وأكّد إنه مش خلل جديد). الإصلاح بره نطاق شريحة ٤.٤ —
    //    مسجّل في التقرير النهائي، مش متجاهل بصمت.
    expect($response->allowed())->toBeFalse()
        ->and($response->message())->not->toBeEmpty();
});

it('القدرة عندها تسمية في اللغتين — مختلفة عن بعضها', function (): void {
    // ⚠️ بنتحقق من وجود المدخلين في ملفات اللغة نفسها مباشرة، مش عبر
    //    permission_label() (خلل سابق موصّف فوق).
    $arabic = require lang_path('ar/authorization.php');
    $english = require lang_path('en/authorization.php');

    expect($arabic['pages']['require.two_factor'] ?? null)->not->toBeNull()
        ->and($english['pages']['require.two_factor'] ?? null)->not->toBeNull()
        ->and($arabic['pages']['require.two_factor'])->not->toBe($english['pages']['require.two_factor']);
});
