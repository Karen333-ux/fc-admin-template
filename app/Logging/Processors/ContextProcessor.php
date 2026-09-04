<?php

declare(strict_types=1);

namespace App\Logging\Processors;

use Illuminate\Support\Facades\Auth;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Infrastructure\Logging\Redactor;

/**
 * بيحط السياق اللي بيتحسب **وقت كتابة السطر** في كل سجل. (docs/11 بند ١)
 *
 * ⚠️ ليه معالج مش `Log::shareContext()` بس؟ الترتيب.
 *    `InitializeTenantContext` بيشتغل في `tenantMiddleware` بتاع Filament —
 *    يعني **بعد** مجموعة الميدلوير الأساسية. لو قرأنا المستأجر في ميدلوير
 *    ونشاركه كقيمة ثابتة، كل سطر في الطلب هيطلع `tenant_id: null`، وده
 *    بالظبط اللي تعليق `InitializeTenantContext` بيحذّر منه.
 *
 *    المعالج بيتنفّذ لحظة كتابة كل سجل، فبيقرا المستأجر بعد ما يتضبط —
 *    وكمان بيشتغل جوّه الطوابير من غير أي ميدلوير HTTP.
 *
 * ⚠️ التنقية هنا كمان: نقطة اختناق واحدة أضمن من الاعتماد على إن كل
 *    مستدعي فاكر إنه مايسجّلش كلمة مرور. (docs/11 بند ٣)
 */
final readonly class ContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            extra: [
                ...$record->extra,
                'tenant_id' => app(TenantContext::class)->id(),
                'user_id' => Auth::id(),
                'locale' => app()->getLocale(),
                'env' => app()->environment(),
            ],
            context: app(Redactor::class)->redact($record->context),
        );
    }
}
