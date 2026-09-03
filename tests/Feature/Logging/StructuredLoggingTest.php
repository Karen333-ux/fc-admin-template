<?php

declare(strict_types=1);

use App\Logging\Processors\ContextProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Monolog\Formatter\JsonFormatter;
use Monolog\Level;
use Monolog\LogRecord;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Domain\Models\Tenant;
use Src\Support\Infrastructure\Queue\RestoresLogContext;
use Src\Support\Presentation\Http\Middleware\AssignRequestContext;
use Symfony\Component\HttpFoundation\Response;
use Tests\Fixtures\RecordsLogContextJob;

beforeEach(function (): void {
    Log::flushSharedContext();
    RecordsLogContextJob::reset();
});

/** بيشغّل ميدلوير الطلب ويرجّع الاستجابة */
function runRequestContext(?string $incomingId = null): Response
{
    $request = Request::create('/admin/users', 'GET');

    if ($incomingId !== null) {
        $request->headers->set(AssignRequestContext::HEADER, $incomingId);
    }

    return app(AssignRequestContext::class)->handle($request, fn (): Response => new Response);
}

/** بيمرّر سجل على المعالج ويرجّع نتيجته */
function processRecord(array $context = []): LogRecord
{
    $record = new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'testing',
        level: Level::Info,
        message: 'حدث اختبار',
        context: $context,
    );

    return (new ContextProcessor)($record);
}

// ────────────────────────────────────────────────────────────────
// request_id — docs/11 بند ٢
// ────────────────────────────────────────────────────────────────

it('طلب من غير ترويسة بياخد معرّف متولّد', function (): void {
    runRequestContext();

    $requestId = Log::sharedContext()['request_id'] ?? null;

    expect($requestId)->toBeString()->not->toBeEmpty();
});

it('المعرّف الجاي في الترويسة بيتحافظ عليه', function (): void {
    runRequestContext('req-abc-123');

    expect(Log::sharedContext()['request_id'])->toBe('req-abc-123');
});

it('المعرّف بيرجع في ترويسة الاستجابة', function (): void {
    $response = runRequestContext('req-abc-123');

    expect($response->headers->get(AssignRequestContext::HEADER))->toBe('req-abc-123');
});

it('كل سطور الطلب الواحد بنفس المعرّف', function (): void {
    // ⚠️ الجوهر: معرّف بيتولّد لكل سطر = سطور مالهاش قيمة للتتبّع
    runRequestContext();

    $first = Log::sharedContext()['request_id'];
    $second = Log::sharedContext()['request_id'];

    expect($first)->toBe($second);
});

it('طلبين مختلفين بمعرّفين مختلفين', function (): void {
    runRequestContext();
    $first = Log::sharedContext()['request_id'];

    Log::flushSharedContext();
    runRequestContext();
    $second = Log::sharedContext()['request_id'];

    expect($first)->not->toBe($second);
});

it('معرّف مشبوه من العميل بيترفض ويتولّد بدله', function (): void {
    // ⚠️ القيمة دي بتروح للوج وللاستجابة. سطر جديد أو رمز تحكّم فيها =
    //    حقن في اللوج أو ترويسة ملوّثة.
    foreach (["bad\nid", 'id with spaces', str_repeat('x', 200), '<script>'] as $hostile) {
        Log::flushSharedContext();
        $response = runRequestContext($hostile);

        expect(Log::sharedContext()['request_id'])->not->toBe($hostile)
            ->and($response->headers->get(AssignRequestContext::HEADER))->not->toBe($hostile);
    }
});

// ────────────────────────────────────────────────────────────────
// tenant_id — بيتقرا وقت كتابة السطر
// ────────────────────────────────────────────────────────────────

it('السجل بياخد مستأجر السياق الحالي', function (): void {
    $tenant = Tenant::factory()->create();
    actingWithinTenant($tenant);

    expect(processRecord()->extra['tenant_id'])->toBe($tenant->getKey());
});

it('من غير سياق مستأجر بيسجّل null مش مستأجر مخترع', function (): void {
    // ⚠️ اختبار سلبي: العمليات العامة (أوامر، مهام مجدولة) مالهاش مستأجر،
    //    ومايصحّش الفحص يخترع واحد.
    app(TenantContext::class)->set(null);

    expect(processRecord()->extra)->toHaveKey('tenant_id')
        ->and(processRecord()->extra['tenant_id'])->toBeNull();
});

it('مستأجر أ مابيظهرش في سطور مستأجر ب', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    actingWithinTenant($a);
    $fromA = processRecord()->extra['tenant_id'];

    actingWithinTenant($b);
    $fromB = processRecord()->extra['tenant_id'];

    expect($fromA)->toBe($a->getKey())
        ->and($fromB)->toBe($b->getKey())
        ->and($fromA)->not->toBe($fromB);
});

it('المستخدم واللغة بيتسجّلوا كمان', function (): void {
    $user = User::factory()->create();
    Auth::login($user);
    app()->setLocale('ar');

    $extra = processRecord()->extra;

    expect($extra['user_id'])->toBe($user->getKey())
        ->and($extra['locale'])->toBe('ar')
        ->and($extra)->toHaveKey('env');
});

// ────────────────────────────────────────────────────────────────
// التنقية — docs/11 بند ٣
// ────────────────────────────────────────────────────────────────

it('الحقول الحسّاسة مابتوصلش للوج', function (): void {
    $context = processRecord([
        'password' => 'super-secret',
        'token' => 'abc123',
        'email' => 'keep@example.test',
    ])->context;

    expect($context['password'])->toBe('[REDACTED]')
        ->and($context['token'])->toBe('[REDACTED]')
        // اللي مش حسّاس بيفضل زي ما هو
        ->and($context['email'])->toBe('keep@example.test');
});

it('التنقية بتمشي على المصفوفات المتداخلة', function (): void {
    // شكل شائع: حد بيسجّل حمولة الطلب كاملة
    $context = processRecord([
        'payload' => ['user' => ['name' => 'كريم', 'password' => 'leak-me']],
    ])->context;

    expect($context['payload']['user']['password'])->toBe('[REDACTED]')
        ->and($context['payload']['user']['name'])->toBe('كريم');
});

// ────────────────────────────────────────────────────────────────
// JSON — docs/11 بند ١
// ────────────────────────────────────────────────────────────────

it('قناة json متعرّفة بمنسّق JSON ومعالج السياق', function (): void {
    $json = config('logging.channels.json');

    expect($json['driver'])->toBe('monolog')
        ->and($json['formatter'])->toBe(JsonFormatter::class)
        ->and($json['processors'])->toContain(ContextProcessor::class);
});

it('السجل المنسّق JSON صالح وفيه الحقول المطلوبة', function (): void {
    $tenant = Tenant::factory()->create();
    actingWithinTenant($tenant);
    Log::shareContext(['request_id' => 'req-json-1']);

    $record = processRecord(['request_id' => 'req-json-1']);
    $line = (new JsonFormatter)->format($record);

    $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

    expect($decoded)->toBeArray()
        ->and($decoded['extra']['tenant_id'])->toBe($tenant->getKey())
        ->and($decoded['context']['request_id'])->toBe('req-json-1')
        ->and($decoded)->toHaveKeys(['message', 'level_name', 'datetime', 'channel']);
});

it('قناة التدقيق منفصلة وباحتفاظ أطول', function (): void {
    $audit = config('logging.channels.audit');

    expect($audit['formatter'])->toBe(JsonFormatter::class)
        ->and($audit['handler_with']['filename'])->not
        ->toBe(config('logging.channels.json.handler_with.filename'));
});

// ────────────────────────────────────────────────────────────────
// الطلب ← الطابور — docs/11 بند ٢
// ────────────────────────────────────────────────────────────────

it('معرّف الطلب والمستأجر بيوصلوا للـ Job', function (): void {
    $tenant = Tenant::factory()->create();
    actingWithinTenant($tenant);
    runRequestContext('req-to-job');

    $job = new RecordsLogContextJob;

    expect($job->requestId)->toBe('req-to-job')
        ->and($job->tenantId)->toBe($tenant->getKey());
});

it('السياق بيترجّع قبل تنفيذ الـ Job', function (): void {
    $tenant = Tenant::factory()->create();
    actingWithinTenant($tenant);
    runRequestContext('req-restore');

    $job = new RecordsLogContextJob;

    // نحاكي عامل الطابور: مفيش سياق طلب، والمستأجر اتمسح في Queue::before
    Log::flushSharedContext();
    app(TenantContext::class)->forget();
    app(TenantContext::class)->set(null);

    app(RestoresLogContext::class)->handle($job, function (object $j): void {
        $j->handle();
    });

    expect(RecordsLogContextJob::$seen['request_id'])->toBe('req-restore')
        ->and(RecordsLogContextJob::$seen['tenant_id'])->toBe($tenant->getKey())
        ->and(RecordsLogContextJob::$seen['job'])->toBe(RecordsLogContextJob::class);
});

it('السياق بيتمسح بعد الـ Job', function (): void {
    $tenant = Tenant::factory()->create();
    actingWithinTenant($tenant);
    runRequestContext('req-cleanup');

    $job = new RecordsLogContextJob;

    app(RestoresLogContext::class)->handle($job, fn (): null => null);

    expect(Log::sharedContext())->toBeEmpty()
        ->and(app(TenantContext::class)->id())->toBeNull();
});

it('سياق الـ Job مابيتسربش للـ Job اللي بعدها', function (): void {
    // ⚠️⚠️ العامل بيعيش لآلاف الـ Jobs بنفس الحاوية. تسريب هنا معناه
    //     سطور تحت مستأجر غلط — الخطر رقم ١ في docs/20.
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    actingWithinTenant($a);
    runRequestContext('req-a');
    $jobA = new RecordsLogContextJob;

    actingWithinTenant($b);
    Log::flushSharedContext();
    runRequestContext('req-b');
    $jobB = new RecordsLogContextJob;

    $middleware = app(RestoresLogContext::class);

    $middleware->handle($jobA, fn (object $j) => $j->handle());
    $seenA = RecordsLogContextJob::$seen;

    $middleware->handle($jobB, fn (object $j) => $j->handle());
    $seenB = RecordsLogContextJob::$seen;

    expect($seenA['tenant_id'])->toBe($a->getKey())
        ->and($seenB['tenant_id'])->toBe($b->getKey())
        ->and($seenA['request_id'])->toBe('req-a')
        ->and($seenB['request_id'])->toBe('req-b');
});

it('Job من غير سياق ملتقط مابيستعملش سياق العامل القديم', function (): void {
    // ⚠️ اختبار سلبي: لو الـ Job اتعملت من غير مستأجر، تنفيذها بعد Job
    //    تابعة لمستأجر مايصحّش يورّثها المستأجر ده.
    $tenant = Tenant::factory()->create();

    actingWithinTenant($tenant);
    $withTenant = new RecordsLogContextJob;

    app(TenantContext::class)->set(null);
    Log::flushSharedContext();
    $withoutTenant = new RecordsLogContextJob;

    $middleware = app(RestoresLogContext::class);

    $middleware->handle($withTenant, fn (object $j) => $j->handle());
    $middleware->handle($withoutTenant, fn (object $j) => $j->handle());

    expect(RecordsLogContextJob::$seen['tenant_id'])->toBeNull();
});

it('شغل مش تابع للميدلوير مابيرثش سياق الـ Job اللي قبله', function (): void {
    // ⚠️⚠️ ده الحارس الحقيقي على التنظيف. اختبار «التسريب» فوق بيعدّي حتى
    //     من غير `finally` لأن كل Job بترجّع سياقها بنفسها. السيناريو
    //     الخطير هو **شغل مابيعديش على الميدلوير أصلاً** — مهمة مجدولة،
    //     أو Job عادية — بتشتغل بعد ContextAwareJob على نفس العامل.
    //     من غير التنظيف بترث المستأجر ومعرّف الطلب بتوع اللي قبلها.
    $tenant = Tenant::factory()->create();
    actingWithinTenant($tenant);
    runRequestContext('req-owner');

    $job = new RecordsLogContextJob;

    app(RestoresLogContext::class)->handle($job, fn (object $j) => $j->handle());

    // «الشغل اللي بعده»: بيقرا السياق من غير ما يرجّع حاجة
    expect(Log::sharedContext())->not->toHaveKey('request_id')
        ->and(app(TenantContext::class)->id())->toBeNull()
        ->and(processRecord()->extra['tenant_id'])->toBeNull();
});

it('الـ Job بتتبعت للطابور ومعاها السياق', function (): void {
    $tenant = Tenant::factory()->create();
    actingWithinTenant($tenant);
    runRequestContext('req-dispatch');

    Bus::fake();

    RecordsLogContextJob::dispatch();

    Bus::assertDispatched(
        RecordsLogContextJob::class,
        fn (RecordsLogContextJob $job): bool => $job->requestId === 'req-dispatch'
            && $job->tenantId === $tenant->getKey(),
    );
});

it('الميدلوير متسجّل على اللوحة', function (): void {
    $middleware = Filament\Facades\Filament::getPanel('admin')->getMiddleware();

    expect($middleware)->toContain(AssignRequestContext::class);
});
