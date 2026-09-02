<?php

declare(strict_types=1);

namespace Src\Contexts\Settings\Application;

/**
 * المصدر **الوحيد** لقيم المظهر اللي الثيم بيقراها.
 *
 * كل قيمة بتعدّي على `TenantSettings`، اللي بيبص على إعداد المستأجر الحالي
 * الأول وبيرجع لـ `AppearanceSettings` العام لو مفيش. (docs/05 بند ٤)
 *
 * ⚠️ الكلاس ده في `Application`: **مفيش** Filament ولا Livewire ولا HTTP جواه.
 * الترجمة لشكل Filament بتحصل في طبقة العرض (`AdminPanelProvider`).
 */
final readonly class AppearanceResolver
{
    private const GROUP = 'appearance';

    public function __construct(private TenantSettings $settings) {}

    public function primaryColor(): string
    {
        $color = $this->settings->get(self::GROUP, 'primary_color');

        // احتياطي آمن: لو الإعداد ناقص أو مش نص، مانكسرش اللوحة
        return is_string($color) && $color !== ''
            ? $color
            : (string) config('theme.fallback_brand_color');
    }

    public function fontFamily(): string
    {
        $font = $this->settings->get(self::GROUP, 'font_family');

        return is_string($font) && $font !== ''
            ? $font
            : (string) config('theme.fonts.sans');
    }

    public function allowsThemeSwitch(): bool
    {
        return (bool) $this->settings->get(self::GROUP, 'allow_theme_switch', true);
    }

    /**
     * مسار اللوجو — أو `null` لو مفيش.
     *
     * ⚠️ رفع الملفات لسه ماتعملش (Media Library بره الشريحة دي)، فالقيمة
     * بتفضل `null` والثيم لازم يشتغل عادي من غيرها: Filament بيرجع لاسم
     * العلامة النصي لما اللوجو يبقى `null`.
     */
    public function logoPath(): ?string
    {
        return $this->nullableString('logo_light_path');
    }

    public function darkLogoPath(): ?string
    {
        return $this->nullableString('logo_dark_path');
    }

    public function faviconPath(): ?string
    {
        return $this->nullableString('favicon_path');
    }

    /**
     * نص الحقوق باللغة الحالية، مع الرجوع للغة الاحتياطية.
     */
    public function footerText(string $locale, string $fallbackLocale): string
    {
        $text = $this->settings->get(self::GROUP, 'footer_text', []);

        if (! is_array($text)) {
            return '';
        }

        return (string) ($text[$locale] ?? $text[$fallbackLocale] ?? '');
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->settings->get(self::GROUP, $key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
