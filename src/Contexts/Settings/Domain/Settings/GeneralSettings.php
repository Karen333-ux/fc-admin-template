<?php

declare(strict_types=1);

namespace Src\Contexts\Settings\Domain\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * الإعدادات العامة — **عامة للتثبيت كله، مش لكل مستأجر**. (docs/05 بند ٤)
 *
 * «الإعدادات اللي المستأجر يقدر يغيّرها = المظهر والإشعارات بس. التخزين
 * والبريد والأمان **عامة** (يديرها الـ super_admin)» — والعامة دي منها.
 *
 * يعني: مفيش `tenant_id`، ومفيش مرور على `TenantSettings`، ومفيش دهس
 * لكل مستأجر. لو مستأجر قدر يغيّر `default_locale` أو `maintenance_mode`
 * ده مايبقاش إعداد، ده تجاوز لحدود التثبيت.
 */
final class GeneralSettings extends Settings
{
    /** @var array<string, string> اسم التطبيق مترجم: ['ar' => '...', 'en' => '...'] */
    public array $app_name;

    /** @var array<string, string> */
    public array $app_description;

    public string $support_email;

    public string $support_phone;

    public string $default_locale;

    public string $timezone;

    public bool $maintenance_mode;

    /** @var array<string, string> */
    public array $maintenance_message;

    public static function group(): string
    {
        return 'general';
    }

    /**
     * مفيش أسرار في المجموعة دي.
     *
     * ⚠️ أي إعداد فيه سر (كلمة مرور SMTP، مفتاح API) لازم يبقى في
     * `encrypted()` بتاع مجموعته — `docs/05` بند ٢. القيم هنا كلها
     * بتتعرض في اللوحة عادي.
     *
     * @return list<string>
     */
    public static function encrypted(): array
    {
        return [];
    }
}
