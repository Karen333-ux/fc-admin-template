<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Health\Enums\Status;
use Src\Support\Infrastructure\Health\FailedJobsCountCheck;

/**
 * فحص عدد الوظائف الفاشلة المتراكمة. (docs/13 بند ٥)
 */
function insertFailedJobs(int $count): void
{
    $rows = [];

    for ($i = 0; $i < $count; $i++) {
        $rows[] = [
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\ExampleJob']),
            'exception' => 'RuntimeException: تجربة',
            'failed_at' => now(),
        ];
    }

    if ($rows !== []) {
        DB::table('failed_jobs')->insert($rows);
    }
}

it('بتعدّي لما العدد تحت الحد', function (): void {
    insertFailedJobs(50);

    $result = FailedJobsCountCheck::new()->failWhenFailedJobsCountIsAbove(50)->run();

    expect($result->status)->toBe(Status::ok());
});

it('بتفشل لما العدد يتخطى الحد', function (): void {
    insertFailedJobs(51);

    $result = FailedJobsCountCheck::new()->failWhenFailedJobsCountIsAbove(50)->run();

    expect($result->status)->toBe(Status::failed())
        ->and($result->notificationMessage)->toContain('51');
});

it('الحد قابل للضبط عبر failWhenFailedJobsCountIsAbove', function (): void {
    insertFailedJobs(5);

    $result = FailedJobsCountCheck::new()->failWhenFailedJobsCountIsAbove(3)->run();

    expect($result->status)->toBe(Status::failed());
});
