<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Queue;

use Closure;
use Illuminate\Support\Facades\Log;
use Src\Support\Application\Contracts\TenantContext;

/**
 * بيرجّع سياق الطلب قبل تنفيذ الـ Job وبيمسحه بعده. (docs/11 بند ٢)
 *
 * ⚠️ **المسح في `finally` مش اختياري.** عامل الطابور بيعيش لآلاف الـ Jobs
 *    بنفس الحاوية. Job سابت سياقها ورا معناها إن الـ Job اللي بعدها بتسجّل
 *    تحت `request_id` و`tenant_id` مش بتوعها — تلوّث صامت في اللوج، وأسوأ
 *    منه: `TenantContext` مضبوط على مستأجر غلط، وده الخطر رقم ١ في `docs/20`.
 *
 * ⚠️ الترتيب متحقّق منه: `Queue::before` (اللي بيمسح `TenantContext`)
 *    بيتنفّذ في `Worker::raiseBeforeJobEvent()` **قبل**
 *    `CallQueuedHandler::dispatchThroughMiddleware()`. يعني بنرجّع السياق
 *    بعد المسح مش قبله — لو اتعكس، الـ Job كانت هتشتغل من غير مستأجر.
 */
final class RestoresLogContext
{
    public function handle(object $job, Closure $next): mixed
    {
        $this->restore($job);

        try {
            return $next($job);
        } finally {
            $this->clear();
        }
    }

    private function restore(object $job): void
    {
        $context = [];

        if (property_exists($job, 'requestId') && is_string($job->requestId)) {
            $context['request_id'] = $job->requestId;
        }

        $context['job'] = $job::class;

        Log::shareContext($context);

        if (property_exists($job, 'tenantId')) {
            $tenantId = $job->tenantId;

            app(TenantContext::class)->set(is_int($tenantId) ? $tenantId : null);
        }
    }

    /**
     * ⚠️ الترتيب مقلوب عن المتوقّع بالقصد: **المستأجر الأول، بعدين اللوج.**
     *
     * `TenantContext::syncDependents()` بينادي
     * `Log::shareContext(['tenant_id' => ...])` جوّه `set()` و`forget()`.
     * يعني لو مسحنا اللوج الأول، `forget()` بعده بيرجّع يحط
     * `tenant_id` تاني والسياق مايتمسحش خالص.
     *
     * ⚠️ `withoutContext()` مع `flushSharedContext()`: الأولانية بتمسح
     * السياق من القنوات اللي اتبنت خلاص، والتانية بتمسح مصفوفة المدير.
     * `flushSharedContext()` لوحدها بتسيب القنوات شايلة سياق الـ Job
     * اللي فاتت.
     */
    private function clear(): void
    {
        app(TenantContext::class)->forget();

        Log::withoutContext();
        Log::flushSharedContext();
    }
}
