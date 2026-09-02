<?php

declare(strict_types=1);

namespace Src\Contexts\Settings\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Contexts\Settings\Domain\Models\TenantSetting;

/**
 * @extends Factory<TenantSetting>
 */
final class TenantSettingFactory extends Factory
{
    protected $model = TenantSetting::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        // مفيش tenant_id هنا — الـ trait بيحطه من TenantContext (ADR-020)
        //
        // القيمة الافتراضية مش لون: اللون الأساسي مصدره الوحيد هجرة إعدادات
        // المظهر، وتكراره هنا بيخلّي مصدرين للحقيقة. (CLAUDE.md بند ١)
        return [
            'group' => 'appearance',
            'key' => 'font_family',
            'value' => 'IBM Plex Sans Arabic',
        ];
    }
}
