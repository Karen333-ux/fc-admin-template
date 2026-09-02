<?php

declare(strict_types=1);

namespace Src\Contexts\Settings\Domain\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * إعدادات المظهر **العامة** — بتخص التثبيت كله.
 *
 * المستأجر يقدر يدهس جزء منها عبر `TenantSettings` (docs/05 بند ٤).
 * القاعدة: المستأجر يغيّر المظهر والإشعارات بس؛ التخزين والبريد والأمان عامة.
 */
final class AppearanceSettings extends Settings
{
    public string $primary_color;

    public ?string $logo_light_path;

    public ?string $logo_dark_path;

    public ?string $favicon_path;

    /** light | dark | system */
    public string $default_theme;

    public bool $allow_theme_switch;

    /** expanded | collapsed */
    public string $sidebar_default;

    /** @var array<string, string> نص الحقوق مترجم */
    public array $footer_text;

    public string $font_family;

    public static function group(): string
    {
        return 'appearance';
    }

    /** @return array<int, string> */
    public static function encrypted(): array
    {
        return [];
    }
}
