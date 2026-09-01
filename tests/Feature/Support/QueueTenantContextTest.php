<?php

declare(strict_types=1);

use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Domain\Models\Tenant;
use Tests\Fixtures\RecordTenantContextJob;

beforeEach(function (): void {
    RecordTenantContextJob::reset();
});

it('سياق المستأجر مابيعديش من job للي بعدها', function (): void {
    // ⚠️ ده أخطر تسريب ممكن: `TenantScope` بيرمي على السياق **الفاضي** بس،
    // مش على السياق **البايت**. يعني job نسيت تضبط سياقها كانت هتشتغل على
    // مستأجر الـ job اللي فاتت من غير أي استثناء. (docs/20 الخطر رقم ١)
    $tenant = Tenant::factory()->create();

    // نمثّل job سابقة سابت السياق مضبوط
    app(TenantContext::class)->set($tenant->getKey());
    expect(app(TenantContext::class)->id())->toBe($tenant->getKey());

    RecordTenantContextJob::dispatch();

    expect(RecordTenantContextJob::$ran)->toBeTrue()
        ->and(RecordTenantContextJob::$seenTenantId)->toBeNull();
});

it('الـ job بتشوف السياق اللي بتضبطه بنفسها', function (): void {
    // المسح مابيمنعش job تضبط سياقها — بيمنع بس إنها **ترث** سياق غيرها
    $tenant = Tenant::factory()->create();

    app(TenantContext::class)->set($tenant->getKey());

    dispatch(function () use ($tenant): void {
        app(TenantContext::class)->set($tenant->getKey());
        RecordTenantContextJob::$ran = true;
        RecordTenantContextJob::$seenTenantId = app(TenantContext::class)->id();
    });

    expect(RecordTenantContextJob::$seenTenantId)->toBe($tenant->getKey());
});

it('كل job بتبدأ من سياق فاضي حتى لو اللي قبلها ضبطته', function (): void {
    $tenant = Tenant::factory()->create();

    dispatch(function () use ($tenant): void {
        app(TenantContext::class)->set($tenant->getKey());
    });

    // الـ job اللي بعدها لازم تبدأ نضيفة
    RecordTenantContextJob::dispatch();

    expect(RecordTenantContextJob::$seenTenantId)->toBeNull();
});
