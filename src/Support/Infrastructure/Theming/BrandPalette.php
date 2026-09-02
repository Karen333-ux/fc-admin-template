<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Theming;

use Filament\Support\Colors\Color;

/**
 * آلية توليد سُلَّم لون العلامة وفحص تباينه.
 *
 * 📍 آلية في `Support` — مش قاعدة أعمال ومش مربوطة بسياق. (docs/22 بند ١١)
 *
 * ⚠️ **مابنكتبش مولّد OKLCH بإيدنا.** `docs/06` بند ٢ كان بيوصف
 * `ColorScaleGenerator` بتحويل OKLCH يدوي، بس Filament v5 بيعمل نفس الحاجة
 * بالظبط في `Color::generatePalette()` (بيحوّل لـ OKLCH وبيولّد السُّلَّم).
 * تكرارها معناه مصدرين لحساب اللون — والاتنين هيفرقوا مع أول تحديث. (ADR-021)
 */
final class BrandPalette
{
    /**
     * سُلَّم Filament كامل من درجة ٦٠٠ (لون العلامة).
     *
     * @return array<int|string, string>
     */
    public function scaleFor(string $hex): array
    {
        return Color::hex($this->normalise($hex));
    }

    /**
     * نسبة التباين بين اللون والأبيض حسب WCAG 2.1.
     *
     * بنفحص درجة ٦٠٠ (اللون المُدخَل نفسه) بس: درجة ٧٠٠ أغمق منها،
     * فلو ٦٠٠ عدّت فـ ٧٠٠ بتعدّي حتماً. (docs/06 بند ٢)
     */
    public function contrastAgainstWhite(string $hex): float
    {
        $luminance = $this->relativeLuminance($this->normalise($hex));

        // (L_أفتح + 0.05) / (L_أغمق + 0.05) — والأبيض لوميناسه 1.0
        return (1.0 + 0.05) / ($luminance + 0.05);
    }

    public function meetsTextContrast(string $hex): bool
    {
        if (! $this->isValidHex($hex)) {
            return false;
        }

        return $this->contrastAgainstWhite($hex) >= (float) config('theme.min_contrast_ratio');
    }

    public function isValidHex(string $hex): bool
    {
        return preg_match('/^#?[0-9A-Fa-f]{6}$/', trim($hex)) === 1;
    }

    /** لومينانس نسبي حسب WCAG 2.1 (sRGB) */
    private function relativeLuminance(string $hex): float
    {
        [$r, $g, $b] = $this->toRgb($hex);

        $channel = static function (float $value): float {
            $value /= 255;

            return $value <= 0.03928
                ? $value / 12.92
                : (($value + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }

    /** @return array{0: float, 1: float, 2: float} */
    private function toRgb(string $hex): array
    {
        $hex = ltrim($this->normalise($hex), '#');

        return [
            (float) hexdec(substr($hex, 0, 2)),
            (float) hexdec(substr($hex, 2, 2)),
            (float) hexdec(substr($hex, 4, 2)),
        ];
    }

    private function normalise(string $hex): string
    {
        $hex = trim($hex);

        return str_starts_with($hex, '#') ? $hex : '#'.$hex;
    }
}
