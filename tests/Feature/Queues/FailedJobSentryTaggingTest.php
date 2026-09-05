<?php

declare(strict_types=1);

use Illuminate\Queue\Events\JobFailed;
use RuntimeException;
use Sentry\Event;
use Sentry\State\Scope;
use Src\Support\Infrastructure\Queue\TagFailedJobForSentry;

/**
 * وسم queue على سياق Sentry وقت فشل أي job. (docs/13 بند ٥)
 *
 * ⚠️ نداء مباشر بدل event() بالقصد — نفس أسلوب `SentryScopeTest::EnrichSentryScope`
 *    بالظبط. `event()` هيشغّل listeners Horizon الداخلية المسجّلة على
 *    JobFailed كمان (`ForgetJobTimer` وغيرها)، اللي محتاجة job حقيقي
 *    بـ `getJobId()`. النداء المباشر بيختبر منطق الوسم لوحده.
 *
 * ⚠️ الالتقاط التلقائي للاستثناء نفسه جاي من `sentry/sentry-laravel`
 *    (بيتحقن على exception handler، مش حاجة بنينها هنا).
 */
it('وسم queue بيتحط على سياق Sentry وقت فشل job في critical', function (): void {
    $job = new class
    {
        public function getQueue(): string
        {
            return 'critical';
        }
    };

    (new TagFailedJobForSentry)->handle(new JobFailed('redis', $job, new RuntimeException('فشل تجريبي')));

    $event = Event::createEvent();

    \Sentry\configureScope(function (Scope $scope) use ($event): void {
        $scope->applyToEvent($event);
    });

    expect($event->getTags())->toHaveKey('queue', 'critical');
});
