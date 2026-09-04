<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Spatie\Activitylog\Models\Activity;

/**
 * أمر التقليم الدوري — `log_name = security/financial` مايتحذفوش أبداً،
 * وآمن يتكرّر. (docs/11 بند ١٠)
 */
function makeActivity(string $logName, string $event, Carbon $createdAt): Activity
{
    $activity = new Activity;
    $activity->log_name = $logName;
    $activity->event = $event;
    $activity->description = 'اختبار';
    $activity->created_at = $createdAt;
    $activity->updated_at = $createdAt;
    $activity->save();

    return $activity;
}

it('بيحذف السطور الأقدم من مدة الاحتفاظ العادية', function (): void {
    $old = makeActivity('identity', 'created', now()->subMonths(13));

    Artisan::call('activitylog:prune');

    expect(Activity::query()->whereKey($old->getKey())->exists())->toBeFalse();
});

it('السطور الأحدث من مدة الاحتفاظ بتفضل', function (): void {
    $recent = makeActivity('identity', 'created', now()->subMonths(2));

    Artisan::call('activitylog:prune');

    expect(Activity::query()->whereKey($recent->getKey())->exists())->toBeTrue();
});

it('log_name = security مايتحذفش أبداً حتى لو قديم جداً', function (): void {
    $security = makeActivity('security', 'deleted', now()->subYears(5));

    Artisan::call('activitylog:prune');

    expect(Activity::query()->whereKey($security->getKey())->exists())->toBeTrue();
});

it('log_name = financial مايتحذفش أبداً حتى لو قديم جداً', function (): void {
    $financial = makeActivity('financial', 'created', now()->subYears(5));

    Artisan::call('activitylog:prune');

    expect(Activity::query()->whereKey($financial->getKey())->exists())->toBeTrue();
});

it('تشغيله مرتين متتاليتين آمن — مفيش أثر إضافي', function (): void {
    makeActivity('identity', 'created', now()->subMonths(13));
    makeActivity('security', 'deleted', now()->subYears(2));

    Artisan::call('activitylog:prune');
    $afterFirst = Activity::query()->count();

    Artisan::call('activitylog:prune');
    $afterSecond = Activity::query()->count();

    expect($afterSecond)->toBe($afterFirst);
});

it('--dry بيعرض العدد من غير ما يحذف', function (): void {
    $old = makeActivity('identity', 'created', now()->subMonths(13));

    Artisan::call('activitylog:prune', ['--dry' => true]);

    expect(Activity::query()->whereKey($old->getKey())->exists())->toBeTrue();
});

it('مدة الاحتفاظ قابلة للضبط عبر الكونفيج', function (): void {
    config(['activitylog.retention_months' => 1]);

    $old = makeActivity('identity', 'created', now()->subMonths(2));

    Artisan::call('activitylog:prune');

    expect(Activity::query()->whereKey($old->getKey())->exists())->toBeFalse();
});

it('أمر التقليم مسجّل في الجدولة الشهرية', function (): void {
    $schedule = app(Schedule::class);

    $found = collect($schedule->events())
        ->contains(fn ($event) => str_contains($event->command ?? '', 'activitylog:prune'));

    expect($found)->toBeTrue();
});
