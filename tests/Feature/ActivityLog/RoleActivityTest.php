<?php

declare(strict_types=1);

use Src\Contexts\Identity\Domain\Models\User;
use Src\Support\Domain\Models\Tenant;

/**
 * تصعيد الصلاحيات — أعلى خطر أمني في `CLAUDE.md`. مفيش شاشة إسناد أدوار
 * في الواجهة لسه، لكن الحدث بيتطلق من `assignRole()/removeRole()` نفسها —
 * أي كود حالي أو مستقبلي بينادي الميثودز دي بيتسجّل. (docs/11 بند ٥)
 */
beforeEach(function (): void {
    actingWithinTenant(Tenant::factory()->create());
});

it('إسناد دور بيسجّل نشاط role_attached تحت security', function (): void {
    $user = User::factory()->create();

    $user->assignRole('editor');

    $activity = $user->activitiesAsSubject()->where('event', 'role_attached')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->log_name)->toBe('security')
        ->and($activity->properties['roles'] ?? [])->not->toBeEmpty();
});

it('إزالة دور بيسجّل نشاط role_detached تحت security', function (): void {
    $user = User::factory()->create();
    $user->assignRole('editor');

    $user->removeRole('editor');

    $activity = $user->activitiesAsSubject()->where('event', 'role_detached')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->log_name)->toBe('security');
});

it('من غير events_enabled الحدث ما كانش هيتطلق أصلاً', function (): void {
    // توثيق التبعية — لو الإعداد ده رجع false، التسجيل كله بيقف بصمت
    expect(config('permission.events_enabled'))->toBeTrue();
});
