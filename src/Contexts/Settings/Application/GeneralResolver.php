<?php

declare(strict_types=1);

namespace Src\Contexts\Settings\Application;

use Src\Contexts\Settings\Domain\Settings\GeneralSettings;

/**
 * المصدر الوحيد لقيم الإعدادات العامة اللي طبقة العرض بتقراها.
 *
 * ⚠️ الفرق الجوهري عن [AppearanceResolver]: ده **مابيعدّيش على
 * `TenantSettings`**. المظهر بيتدهس لكل مستأجر، أما الإعدادات العامة
 * فبتتقرا من `GeneralSettings` مباشرةً — إعداد واحد للتثبيت كله
 * (docs/05 بند ٤).
 *
 * ⚠️ الكلاس ده في `Application`: مفيش Filament ولا Livewire ولا HTTP جواه.
 * الترجمة لشكل Filament بتحصل في `AdminPanelProvider`.
 */
final readonly class GeneralResolver
{
    public function __construct(private GeneralSettings $settings) {}

    /**
     * اسم التطبيق باللغة الحالية، مع الرجوع للغة الاحتياطية.
     *
     * الاحتياطي الأخير `config('app.name')` مقصود: `brandName` بيتنادى في
     * كل صفحة، ومفتاح لغة ناقص مايصحّش يقلب اللوحة صفحة خطأ.
     */
    public function appName(string $locale, string $fallbackLocale): string
    {
        $name = $this->translated($this->settings->app_name, $locale, $fallbackLocale);

        return $name !== '' ? $name : (string) config('app.name');
    }

    public function appDescription(string $locale, string $fallbackLocale): string
    {
        return $this->translated($this->settings->app_description, $locale, $fallbackLocale);
    }

    public function isUnderMaintenance(): bool
    {
        return $this->settings->maintenance_mode;
    }

    public function maintenanceMessage(string $locale, string $fallbackLocale): string
    {
        return $this->translated($this->settings->maintenance_message, $locale, $fallbackLocale);
    }

    /** @param array<string, string> $values */
    private function translated(array $values, string $locale, string $fallbackLocale): string
    {
        return (string) ($values[$locale] ?? $values[$fallbackLocale] ?? '');
    }
}
