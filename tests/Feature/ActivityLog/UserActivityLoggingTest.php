<?php

declare(strict_types=1);

use Src\Contexts\Identity\Domain\Models\User;
use Src\Support\Domain\Models\Tenant;

/**
 * تسجيل نشاط موديل User الأساسي — created/updated/deleted/restored،
 * logOnly، logOnlyDirty، dontLogEmptyChanges، والوصف المترجم. (docs/11 بند ٣)
 */
beforeEach(function (): void {
    actingWithinTenant(Tenant::factory()->create());
});

it('إنشاء مستخدم بيسجّل نشاط created', function (): void {
    $user = User::factory()->create();

    $activity = $user->activitiesAsSubject()->where('event', 'created')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->log_name)->toBe('identity');
});

it('تعديل مستخدم بيسجّل نشاط updated بالحقول المسموحة بس', function (): void {
    $user = User::factory()->create(['name' => 'قبل']);

    $user->update(['name' => 'بعد']);

    $activity = $user->activitiesAsSubject()->where('event', 'updated')->latest()->first();

    expect($activity)->not->toBeNull();

    $attributes = $activity->attribute_changes['attributes'] ?? [];
    $old = $activity->attribute_changes['old'] ?? [];

    expect($attributes)->toHaveKey('name')
        ->and($attributes['name'])->toBe('بعد')
        ->and($old['name'])->toBe('قبل')
        // logOnly(['name','email','locale']) — password ما يتسجّلش أبداً
        ->and($attributes)->not->toHaveKey('password');
});

it('تعديل حقل مش في logOnly مايتسجّلش', function (): void {
    $user = User::factory()->create();
    $before = $user->activitiesAsSubject()->count();

    // email_verified_at مش من ضمن logOnly(['name','email','locale'])
    $user->forceFill(['email_verified_at' => now()])->save();

    // dontLogEmptyChanges: مفيش تغيير في الحقول المسموحة → مفيش نشاط جديد
    expect($user->activitiesAsSubject()->count())->toBe($before);
});

it('حفظ من غير تغيير فعلي مايسجّلش نشاط (dontLogEmptyChanges)', function (): void {
    $user = User::factory()->create(['name' => 'ثابت']);
    $before = $user->activitiesAsSubject()->count();

    $user->update(['name' => 'ثابت']);

    expect($user->activitiesAsSubject()->count())->toBe($before);
});

it('حذف مستخدم (soft delete) بيسجّل نشاط deleted تحت security', function (): void {
    $user = User::factory()->create();

    $user->delete();

    $activity = $user->activitiesAsSubject()->where('event', 'deleted')->first();

    expect($activity)->not->toBeNull()
        // الحذف حساس — بيتحوّل لـ security بغض النظر عن useLogName الموديل
        ->and($activity->log_name)->toBe('security');
});

it('استرجاع مستخدم بيسجّل نشاط restored تحت security', function (): void {
    $user = User::factory()->create();
    $user->delete();

    $user->restore();

    $activity = $user->activitiesAsSubject()->where('event', 'restored')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->log_name)->toBe('security');
});

it('الوصف مترجم مش نص خام', function (): void {
    $user = User::factory()->create();

    $activity = $user->activitiesAsSubject()->where('event', 'created')->first();

    expect($activity->description)->toBe(__('audit.events.user.created'))
        ->and($activity->description)->not->toContain('audit.events');
});

it('الوصف بيتغيّر حسب اللغة النشطة وقت الحدث', function (): void {
    app()->setLocale('en');
    $user = User::factory()->create();

    $activity = $user->activitiesAsSubject()->where('event', 'created')->first();

    expect($activity->description)->toBe(trans('audit.events.user.created', [], 'en'));

    app()->setLocale('ar');
});
