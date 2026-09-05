<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Src\Support\Domain\Models\Tenant;
use Src\Support\Infrastructure\Queue\FailedJob;
use Src\Support\Presentation\Filament\Pages\FailedJobsPage;

/**
 * صفحة الوظائف الفاشلة — نفس صلاحية access.horizon، مفيش صلاحية
 * كتالوج جديدة. (docs/13 بند ٥)
 */
function failedJobsContext(Tenant $tenant, object $user): void
{
    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);
    actingWithinTenant($tenant);
}

/**
 * إدراج مباشر عبر DB::table — موديل FailedJob بمنع mass-assignment
 * افتراضياً بالقصد (مفيش fillable، القراءة والحذف بس). (docs/13 بند ٥)
 *
 * ⚠️ `connection = database` مش `redis`: القيمة دي هي اللي `queue:retry`
 *    هيدفع عليها تاني (`Queue::connection($job->connection)->pushRaw()`)،
 *    و`database` بتفضل معزولة داخل `RefreshDatabase` زي أي جدول تاني —
 *    نفس سبب `QUEUE_CONNECTION=sync` في `phpunit.xml` (docs/23 بند ٧-٤):
 *    الاختبارات مايتلوثوش من بعض عبر Redis حقيقي.
 */
function insertFailedJobRow(string $queue = 'critical'): FailedJob
{
    $uuid = (string) Str::uuid();

    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => $queue,
        'payload' => json_encode(['displayName' => 'App\\Jobs\\ExampleJob']),
        'exception' => 'RuntimeException: مشكلة تجريبية',
        'failed_at' => now(),
    ]);

    return FailedJob::query()->where('uuid', $uuid)->firstOrFail();
}

it('مصفوفة أدوار access.horizon بتتحكم في FailedJobsPage::canAccess', function (string $role, bool $expected): void {
    $tenant = Tenant::factory()->create();
    $user = userWithRole($role, $tenant);

    failedJobsContext($tenant, $user);

    expect(FailedJobsPage::canAccess())->toBe($expected, "الدور {$role}");
})->with([
    ['super_admin', true],
    ['admin', true],
    ['editor', false],
    ['viewer', false],
]);

it('يمنع الوصول بالـ URL المباشر لمن لا يملك access.horizon', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = userWithRole('viewer', $tenant);

    $this->actingAs($viewer)
        ->get(FailedJobsPage::getUrl(tenant: $tenant))
        ->assertForbidden();
});

it('الصفحة بتعرض الوظائف الفاشلة الموجودة', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    failedJobsContext($tenant, $admin);

    insertFailedJobRow();

    Livewire::actingAs($admin)
        ->test(FailedJobsPage::class)
        ->assertOk()
        ->loadTable()
        ->assertSee('App\\Jobs\\ExampleJob');
});

it('زرار إعادة المحاولة بيرجّع الوظيفة للطابور ويشيلها من failed_jobs', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    failedJobsContext($tenant, $admin);

    $record = insertFailedJobRow();

    Livewire::actingAs($admin)
        ->test(FailedJobsPage::class)
        ->callTableAction('retry', $record);

    expect(DB::table('failed_jobs')->where('uuid', $record->uuid)->exists())->toBeFalse()
        ->and(DB::table('jobs')->where('queue', 'critical')->exists())->toBeTrue();
});
