<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Checks\BackupsCheck;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DatabaseConnectionCountCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\HorizonCheck;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\RedisCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Enums\Status;
use Spatie\Health\Facades\Health;
use Src\Support\Domain\Models\Tenant;
use Src\Support\Infrastructure\Health\FailedJobsCountCheck;
use Src\Support\Presentation\Filament\Pages\HealthPage;

/**
 * صحة النظام. (docs/11 بند ٧)
 */
it('الفحوصات الأساسية مسجّلة كلها', function (): void {
    $classes = Health::registeredChecks()->map(fn (Check $check): string => $check::class);

    expect($classes)->toContain(
        DatabaseCheck::class,
        RedisCheck::class,
        CacheCheck::class,
        QueueCheck::class,
        UsedDiskSpaceCheck::class,
        ScheduleCheck::class,
        DatabaseConnectionCountCheck::class,
        FailedJobsCountCheck::class,
        BackupsCheck::class,
        OptimizedAppCheck::class,
        DebugModeCheck::class,
        EnvironmentCheck::class,
    );
});

it('Horizon لسه مؤجّلة — مفيش بنية تحتية ليها دلوقتي', function (): void {
    $classes = Health::registeredChecks()->map(fn (Check $check): string => $check::class);

    expect($classes)->not->toContain(HorizonCheck::class);
});

it('النسخ الاحتياطي بقت مسجّلة على ديسك s3-private', function (): void {
    $classes = Health::registeredChecks()->map(fn (Check $check): string => $check::class);

    expect($classes)->toContain(BackupsCheck::class);
});

it('BackupsCheck بتنجح لما فيه نسخة احتياطية على s3-private وبتفشل من غيرها', function (): void {
    Storage::fake('s3-private');

    $appName = (string) config('backup.backup.name');

    $failing = app(BackupsCheck::class)->onDisk('s3-private')->locatedAt($appName)->run();
    expect($failing->status)->toBe(Status::failed());

    Storage::disk('s3-private')->put("{$appName}/backup.zip", 'x');

    $passing = app(BackupsCheck::class)->onDisk('s3-private')->locatedAt($appName)->run();
    expect($passing->status)->toBe(Status::ok());
});

// ⚠️ الراوت بتاع /health بيتسجّل وقت إقلاع HealthServiceProvider **مرة
// واحدة بس**، وبيقرا `config('health.oh_dear_endpoint.secret')` وقتها —
// تغيير الكونفيج جوه الاختبار بعد الإقلاع مايأثّرش على تسجيل الراوت.
// القيمة الحقيقية جاية من `HEALTH_CHECK_SECRET` في phpunit.xml.
it('/health من غير مفتاح سري بيترفض', function (): void {
    $this->get('/health')->assertForbidden();
});

it('/health بمفتاح غلط بيترفض', function (): void {
    $this->withHeaders(['oh-dear-health-check-secret' => 'wrong'])
        ->get('/health')
        ->assertForbidden();
});

it('/health بمفتاح صح بيرجّع JSON بحالة كل فحص', function (): void {
    // always_send_fresh_results=true بيشغّل كل الفحوصات فعلياً على كل
    // طلب — بما فيها BackupsCheck على s3-private. fake() يمنع نداء S3
    // حقيقي (نفس أسلوب MediaTemporaryUrlTest). (docs/11 بند ٩)
    Storage::fake('s3-private');

    $response = $this->withHeaders(['oh-dear-health-check-secret' => 'testing-health-check-secret'])
        ->get('/health');

    $response->assertSuccessful();

    $json = $response->json();

    expect($json)->toHaveKey('checkResults')
        ->and($json['checkResults'])->not->toBeEmpty();
});

it('صفحة صحة النظام محمية بـ access.health', function (string $role, bool $expected): void {
    $tenant = Tenant::factory()->create();
    $user = userWithRole($role, $tenant);

    actingWithinTenant($tenant);

    expect(Gate::forUser($user)->allows('access.health'))->toBe($expected, "الدور {$role}");
})->with([
    ['super_admin', true],
    ['admin', true],
    ['editor', false],
    ['viewer', false],
]);

it('صفحة صحة النظام بتفتح فعلاً وبتعرض نتايج الفحوصات', function (): void {
    // HealthPage::mount() بينده RunHealthChecksCommand فعلياً — نفس
    // سبب fake() فوق. (docs/11 بند ٩)
    Storage::fake('s3-private');

    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    Auth::login($admin);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);
    actingWithinTenant($tenant);

    Livewire::actingAs($admin)
        ->test(HealthPage::class)
        ->assertOk()
        ->assertSee(__('health.title'));
});
