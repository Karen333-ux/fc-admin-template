<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Actions\LogActivityAction;
use Spatie\Activitylog\Models\Activity;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Domain\Models\Tenant;
use Src\Support\Infrastructure\Queue\RestoresLogContext;
use Tests\Fixtures\RecordsLogContextJob;

/**
 * تخصيب سطر النشاط: tenant_id/request_id/ip، والتنقية — عبر
 * `LogActivityAction::beforeLogging()` مش `tapActivity()` (مش موجودة في
 * v5.1.0 — اتفحص السورس). (docs/11 بند ٤ و ٦)
 */
beforeEach(function (): void {
    Log::flushSharedContext();
});

it('كل سطر نشاط بيتحقن فيه tenant_id/request_id/ip', function (): void {
    $tenant = Tenant::factory()->create();
    actingWithinTenant($tenant);

    Log::shareContext(['request_id' => 'req-test-1', 'ip' => '10.0.0.5']);

    $user = User::factory()->create();

    $activity = $user->activitiesAsSubject()->where('event', 'created')->first();

    expect($activity->properties['tenant_id'])->toBe($tenant->getKey())
        ->and($activity->properties['request_id'])->toBe('req-test-1')
        ->and($activity->properties['ip'])->toBe('10.0.0.5');
});

it('من غير سياق مستأجر — tenant_id فاضي', function (): void {
    $user = User::factory()->create();

    $activity = $user->activitiesAsSubject()->where('event', 'created')->first();

    expect($activity->properties['tenant_id'])->toBeNull();
});

it('سياق الطابور بيتنقل لسطور النشاط اللي الـ Job بتسجّلها', function (): void {
    // Job بتنادي إجراء بيسجّل نشاط — لازم يشيل tenant_id بتاع الـ Job مش الفاضي
    $tenant = Tenant::factory()->create();
    actingWithinTenant($tenant);

    $job = new RecordsLogContextJob;
    $job->tenantId = $tenant->getKey();
    $job->requestId = 'req-from-queue';

    app(TenantContext::class)->forget();
    Log::flushSharedContext();

    (new RestoresLogContext)->handle($job, function () use ($tenant): void {
        // جوّه تنفيذ الـ Job — نفس اللي بيحصل حقيقي وقت تسجيل نشاط من Job
        $user = User::factory()->create();

        $activity = $user->activitiesAsSubject()->where('event', 'created')->first();

        expect($activity->properties['tenant_id'])->toBe($tenant->getKey())
            ->and($activity->properties['request_id'])->toBe('req-from-queue');
    });
});

it('التنقية بتشتغل على properties كمان — نفس config(logging.redact)', function (): void {
    activity('identity')
        ->withProperties(['password' => 'leak-me', 'note' => 'خليها']) // password من ضمن logging.redact
        ->log('اختبار');

    $activity = Activity::query()->latest()->first();

    expect($activity->properties['password'])->toBe('[REDACTED]')
        ->and($activity->properties['note'])->toBe('خليها');
});

it('التنقية بتمشي على attribute_changes المتداخلة', function (): void {
    // لو حد ضاف password لـ logOnly بالغلط، لازم تتنقّى برضه — شبكة أمان تانية.
    // بننادي LogActivityAction مباشرة — نفس المسار الحقيقي لأي نشاط بيتسجّل.
    $activity = new Activity;
    $activity->log_name = 'identity';
    $activity->attribute_changes = collect([
        'attributes' => ['password' => 'leak-me', 'name' => 'كريم'],
        'old' => ['password' => 'old-secret', 'name' => 'قديم'],
    ]);

    (new LogActivityAction)->execute($activity, 'اختبار');

    expect($activity->attribute_changes['attributes']['password'])->toBe('[REDACTED]')
        ->and($activity->attribute_changes['old']['password'])->toBe('[REDACTED]')
        ->and($activity->attribute_changes['attributes']['name'])->toBe('كريم');
});
