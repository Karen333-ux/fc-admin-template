<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Queue;

use Illuminate\Queue\Events\JobFailed;
use Sentry\State\Scope;

/**
 * وسم queue على سياق Sentry وقت فشل أي job — نفس أسلوب
 * `EnrichSentryScope` بالظبط، بس على مستوى الطابور مش الطلب. (docs/13 بند ٥)
 *
 * ⚠️ التقاط الاستثناء نفسه أصلاً تلقائي — `sentry/sentry-laravel` بيلزّق
 *    نفسه على exception handler وبيبلّغ عن أي استثناء queue worker بيرمّره
 *    (`Worker::runJob()`، `$this->exceptions->report($e)`). الوسم هنا بس
 *    عشان تقدر تعمل Sentry alert rule فعلي على `queue:critical` — بدون
 *    tag، مفيش حاجة قابلة للفلترة غير الـ breadcrumb (مش مدعومة في قواعد
 *    التنبيه بنفس سهولة الـ tags).
 *
 * ⚠️ الترتيب سليم: `JobFailed` بيتطلق جوّه `Worker::process()` **قبل**
 *    ما `runJob()` يمسك الاستثناء المعاد رميه وينده `report()` — فالوسم
 *    بيتحط على الـ scope الحالي قبل ما Sentry يلتقط الحدث. (اتحقق من
 *    `vendor/laravel/framework/src/Illuminate/Queue/Worker.php`)
 *
 * ⚠️ كلاس مستقل مش closure جوّه AppServiceProvider بالقصد: `Queue::failing()`
 *    عبر event dispatcher بيشغّل كل الـ listeners المسجّلة على JobFailed
 *    مع بعض — بما فيها listeners Horizon الداخلية اللي محتاجة job حقيقي
 *    (`getJobId()` وغيرها). كلاس منفصل بيسمح باختبار المنطق ده لوحده،
 *    بنداء مباشر زي `SentryScopeTest::EnrichSentryScope`، من غير ما نمرّ
 *    على event() ونصطدم بـ listeners Horizon.
 */
final class TagFailedJobForSentry
{
    public function handle(JobFailed $event): void
    {
        \Sentry\configureScope(static function (Scope $scope) use ($event): void {
            $scope->setTag('queue', $event->job->getQueue());
        });
    }
}
