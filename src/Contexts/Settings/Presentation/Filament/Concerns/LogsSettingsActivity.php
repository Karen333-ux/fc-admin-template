<?php

declare(strict_types=1);

namespace Src\Contexts\Settings\Presentation\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Src\Contexts\Settings\Application\LogsSettingsChange;

/**
 * بتربط دورة حياة `SettingsPage::save()` بتسجيل النشاط. (docs/11 بند ٥)
 *
 * القرار المعتمد: خطاف `afterSave()` صريح/يدوي — مش تحويل الإعدادات
 * لموديل Eloquent ولا أي آلية بديلة. الحساب نفسه في `LogsSettingsChange`
 * (طبقة Application) — الصفحة هنا بس بتوصّل قبل/بعد.
 *
 * ⚠️ اللقطة القديمة بتتاخد في `mutateFormDataBeforeSave()` **قبل**
 *    `SettingsPage::save()` تجيب نسخة جديدة من الإعدادات وتحفظ فوقها —
 *    فهي آخر فرصة نشوف القيم القديمة. `afterSave()` بيجيب نسخة جديدة
 *    بعد الحفظ عشان يقارن. (اتفحص `SettingsPage::save()` بالسورس)
 */
trait LogsSettingsActivity
{
    /** @var array<string, mixed> */
    private array $activityLogBeforeSnapshot = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->activityLogBeforeSnapshot = app(static::getSettings())->toArray();

        return $data;
    }

    protected function afterSave(): void
    {
        $after = app(static::getSettings())->toArray();
        $causer = Auth::user();

        app(LogsSettingsChange::class)->handle(
            static::getSettings(),
            $this->activityLogBeforeSnapshot,
            $after,
            $causer instanceof Model ? $causer : null,
            __('audit.events.settings.updated'),
        );
    }
}
