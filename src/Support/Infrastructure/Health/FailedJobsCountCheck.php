<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Health;

use Illuminate\Support\Facades\DB;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * عدد سطور failed_jobs المتراكمة. (docs/13 بند ٥)
 *
 * ⚠️ مفيش check جاهزة لده في spatie/laravel-health — أقرب حاجة
 *    `QueueCheck` بتفحص إن الطابور بيتحرك، مش عدد الفاشلة المتراكمة.
 */
final class FailedJobsCountCheck extends Check
{
    private int $failWhenAbove = 50;

    public function failWhenFailedJobsCountIsAbove(int $count): self
    {
        $this->failWhenAbove = $count;

        return $this;
    }

    public function run(): Result
    {
        $count = DB::table('failed_jobs')->count();

        $result = Result::make()->shortSummary((string) $count);

        if ($count > $this->failWhenAbove) {
            return $result->failed(__('health.failed_jobs.failing', [
                'count' => $count,
                'threshold' => $this->failWhenAbove,
            ]));
        }

        return $result->ok(__('health.failed_jobs.passing', ['count' => $count]));
    }
}
