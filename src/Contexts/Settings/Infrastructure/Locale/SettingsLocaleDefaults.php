<?php

declare(strict_types=1);

namespace Src\Contexts\Settings\Infrastructure\Locale;

use Src\Contexts\Settings\Domain\Settings\GeneralSettings;
use Src\Support\Application\Contracts\LocaleDefaults;

/**
 * الجسر بين عقد `Support` وإعدادات سياق `Settings`. (نفس نمط ADR-022)
 *
 * الاتجاه: **السياق** بيعتمد على `Support`، مش العكس — عشان كده الكلاس
 * عايش في `Contexts\Settings`. سياق `Identity` بيشوف العقد بس، فمفيش
 * سياق بيستورد سياق.
 *
 * اللغة الافتراضية إعداد **عام** للتثبيت (docs/05 بند ٤)، مش لكل مستأجر —
 * فمفيش مرور على `TenantSettings` هنا.
 */
final readonly class SettingsLocaleDefaults implements LocaleDefaults
{
    public function __construct(private GeneralSettings $settings) {}

    public function default(): string
    {
        $locale = $this->settings->default_locale;

        // احتياطي أخير: إعداد ناقص مايخلّيش الإشعارات تطلع بلغة فاضية
        return $locale !== '' ? $locale : (string) config('app.fallback_locale');
    }
}
