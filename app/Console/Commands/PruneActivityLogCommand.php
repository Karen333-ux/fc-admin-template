<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * تقليم سجل النشاط القديم. (docs/11 بند ١٠)
 *
 * ⚠️ `log_name` بـ `security` أو `financial` **مايتحذفوش أبداً** — تصعيد
 *    الصلاحيات، الحذف، محاولات الدخول الفاشلة، وتغيير الإعدادات كلها
 *    مسجّلة تحت `security` (`AppServiceProvider::enrichActivityLog()` و
 *    `LogRoleActivity`/`LogFailedLoginActivity`/`LogsSettingsChange`).
 *
 * ⚠️ آمن يتكرّر: الحذف مبني على `created_at` بس، فتشغيله مرتين متتاليتين
 *    التشغيلة التانية مالهاش أثر إضافي.
 *
 * ⚠️ الأمر مقصود إنه لا يعتمد على مستأجر — سجل النشاط عبر كل المستأجرين،
 *    والتقليم عملية نظام مش عملية مستأجر واحد.
 */
final class PruneActivityLogCommand extends Command
{
    private const EXEMPT_LOG_NAMES = ['security', 'financial'];

    protected $signature = 'activitylog:prune {--dry : عرض العدد من غير حذف}';

    protected $description = 'حذف سجل النشاط الأقدم من مدة الاحتفاظ — ما عدا security وfinancial';

    public function handle(): int
    {
        $months = (int) config('activitylog.retention_months', 12);
        $cutoff = Carbon::now()->subMonths($months);

        $query = Activity::query()
            ->where('created_at', '<', $cutoff)
            ->whereNotIn('log_name', self::EXEMPT_LOG_NAMES);

        $count = $query->count();

        $this->info("سطور أقدم من {$months} شهر (ما عدا security/financial): {$count}");

        if ($this->option('dry') || $count === 0) {
            return self::SUCCESS;
        }

        $query->delete();

        $this->info("اتحذف {$count} سطر.");

        return self::SUCCESS;
    }
}
