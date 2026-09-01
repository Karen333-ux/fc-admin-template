<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Src\Support\Application\Contracts\TenantContext;

/**
 * Job اختبار بس — بتسجّل سياق المستأجر اللي شافته وقت التنفيذ.
 *
 * الشريحة الأولى مافيهاش jobs (docs/23 بند ١)، فمن غير الفيكستشر ده مافيش
 * طريقة نثبت بيها إن السياق مابيعديش من job للي بعدها.
 */
final class RecordTenantContextJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public static ?int $seenTenantId = null;

    public static bool $ran = false;

    public static function reset(): void
    {
        self::$seenTenantId = null;
        self::$ran = false;
    }

    public function handle(TenantContext $context): void
    {
        self::$ran = true;
        self::$seenTenantId = $context->id();
    }
}
