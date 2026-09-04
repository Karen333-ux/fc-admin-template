<?php

declare(strict_types=1);

use Src\Contexts\Identity\Domain\Models\User;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Domain\Models\Tenant;
use Src\Support\Infrastructure\ActivityLog\ActivityLogContext;

/**
 * الاختبار الأخطر في الشريحة — أول ما يتكتب، قبل أي حاجة تانية. (docs/11 بند ٤)
 *
 * جدول `activity_log` مافيهوش `tenant_id`، ومستخدم ممكن يكون عضو في أكتر
 * من مستأجر — فسجل نشاطه ممكن يحمل أحداث مستأجرات تانية كلها على نفس
 * السجل (subject) الواحد. لو `ActivityLogContext::scope()` مش شغّالة صح،
 * أدمن مستأجر B هيشوف تعديلات حصلت في مستأجر A على نفس المستخدم المشترك.
 */
it('نشاط مستخدم مشترك في مستأجرين — كل مستأجر بيشوف نشاطه بس', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $shared = User::factory()->create(['name' => 'قبل']);
    $shared->tenants()->attach([$a->getKey(), $b->getKey()]);

    actingWithinTenant($a);
    $shared->update(['name' => 'اتعدّل في أ']);

    actingWithinTenant($b);
    $shared->update(['name' => 'اتعدّل في ب']);

    // لسه في سياق b — الـ scope المفروض يرشّح على أساسه
    $visible = app(ActivityLogContext::class)
        ->scope($shared->activitiesAsSubject()->getQuery())
        ->get()
        ->map(fn ($activity) => $activity->properties['tenant_id'] ?? null);

    expect($visible)->not->toContain($a->getKey())
        ->and($visible)->toContain($b->getKey());
});

it('التبديل للمستأجر الأول بيرجّع يشوف نشاطه بس', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $shared = User::factory()->create(['name' => 'قبل']);
    $shared->tenants()->attach([$a->getKey(), $b->getKey()]);

    actingWithinTenant($a);
    $shared->update(['name' => 'اتعدّل في أ']);

    actingWithinTenant($b);
    $shared->update(['name' => 'اتعدّل في ب']);

    actingWithinTenant($a);

    $visible = app(ActivityLogContext::class)
        ->scope($shared->activitiesAsSubject()->getQuery())
        ->get()
        ->map(fn ($activity) => $activity->properties['tenant_id'] ?? null);

    expect($visible)->toContain($a->getKey())
        ->and($visible)->not->toContain($b->getKey());
});

it('belongsToCurrentTenant بيرفض نشاط مستأجر تاني', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $shared = User::factory()->create();
    $shared->tenants()->attach([$a->getKey(), $b->getKey()]);

    actingWithinTenant($a);
    $shared->update(['name' => 'اتعدّل في أ']);

    $activity = $shared->activitiesAsSubject()->where('event', 'updated')->latest()->first();

    actingWithinTenant($b);

    expect(app(ActivityLogContext::class)->belongsToCurrentTenant($activity->properties->toArray()))
        ->toBeFalse();
});

it('نشاط من غير tenant_id (زي محاولة دخول فاشلة) ظاهر في كل مستأجرات المستخدم', function (): void {
    // مش ثغرة — بالظبط نفس منطق NotificationOwnership لإشعار على مستوى الحساب
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $shared = User::factory()->create();
    $shared->tenants()->attach([$a->getKey(), $b->getKey()]);

    activity('security')
        ->performedOn($shared)
        ->causedBy(null)
        ->event('login_failed')
        ->log('محاولة دخول فاشلة');

    foreach ([$a, $b] as $tenant) {
        actingWithinTenant($tenant);

        $visible = app(ActivityLogContext::class)
            ->scope($shared->activitiesAsSubject()->getQuery())
            ->get();

        expect($visible->pluck('event'))->toContain('login_failed');
    }
});

it('من غير سياق مستأجر — بس نشاط الحساب العام ظاهر', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $shared = User::factory()->create();
    $shared->tenants()->attach([$a->getKey(), $b->getKey()]);

    actingWithinTenant($a);
    $shared->update(['name' => 'اتعدّل في أ']);

    app(TenantContext::class)->set(null);

    activity('security')->performedOn($shared)->causedBy(null)->event('login_failed')->log('فشل');

    $visible = app(ActivityLogContext::class)
        ->scope($shared->activitiesAsSubject()->getQuery())
        ->get();

    expect($visible->pluck('event'))->toContain('login_failed')
        ->and($visible->pluck('event'))->not->toContain('updated');
});
