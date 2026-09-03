<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Support\Facades\Log;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Infrastructure\Queue\ContextAwareJob;

/**
 * Job اختبار — بيسجّل السياق اللي شافه وقت التنفيذ.
 *
 * بيقيس **السلوك الملاحَظ** (السياق اللي وصل فعلاً) مش تفاصيل التنفيذ.
 */
final class RecordsLogContextJob extends ContextAwareJob
{
    /** @var array<string, mixed> السياق اللي الـ Job شافه — static عشان يعيش بعد التسلسل */
    public static array $seen = [];

    public static function reset(): void
    {
        self::$seen = [];
    }

    public function handle(): void
    {
        /** @var array<string, mixed> $shared */
        $shared = Log::sharedContext();

        self::$seen = [
            'request_id' => $shared['request_id'] ?? null,
            'job' => $shared['job'] ?? null,
            'tenant_id' => app(TenantContext::class)->id(),
        ];
    }
}
