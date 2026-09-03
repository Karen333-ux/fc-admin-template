<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Src\Support\Application\Contracts\TenantContext;

/**
 * الأساس لأي Job محتاج يفضل متتبَّع ومربوط بمستأجره. (docs/11 بند ٢)
 *
 * ⚠️ **السياق بيتلقط في الكونستركتور — يعني وقت الإرسال، جوّه الطلب.**
 *    الطابور مابينقلش أي سياق: `AppServiceProvider::forgetTenantContextBetweenJobs()`
 *    بيمسح `TenantContext` في `Queue::before`، وسياق اللوج المشترك مالوش
 *    وجود أصلاً في عامل الطابور. قراءة أي منهم جوّه `handle()` بتدّي
 *    `null` — أو أسوأ، بتدّي بيانات الـ Job اللي فاتت.
 *
 *    نفس القاعدة اللي ADR-024 سجّلها على الإشعارات، ونفس اللي `CLAUDE.md`
 *    بيقوله: «الطوابير مابتحملش سياق مستأجر — مرّر tenantId صراحةً».
 *
 * ⚠️ الخصائص `public` عشان تتسلسل مع الـ Job. `readonly` مش هينفع هنا:
 *    Laravel بيعيد بناء الكائن من التسلسل من غير ما يعدّي على الكونستركتور.
 */
abstract class ContextAwareJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public ?string $requestId = null;

    public ?int $tenantId = null;

    public ?int $userId = null;

    public function __construct()
    {
        /** @var array<string, mixed> $shared */
        $shared = Log::sharedContext();

        $requestId = $shared['request_id'] ?? null;

        $this->requestId = is_string($requestId) ? $requestId : (string) Str::uuid();
        $this->tenantId = app(TenantContext::class)->id();
        $this->userId = Auth::id();
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new RestoresLogContext];
    }
}
