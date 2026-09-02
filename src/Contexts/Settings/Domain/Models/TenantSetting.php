<?php

declare(strict_types=1);

namespace Src\Contexts\Settings\Domain\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Src\Contexts\Settings\Database\Factories\TenantSettingFactory;
use Src\Support\Infrastructure\Persistence\Concerns\BelongsToTenant;

/**
 * إعداد على مستوى المستأجر — **أول موديل إنتاجي تابع لمستأجر** في القالب.
 *
 * ⚠️ `tenant_id` **بره `$fillable`** عن قصد: الـ trait بيحطه في `creating`،
 * و`TenantScope` بيفلتر بيه كل استعلام. مفيش شرط `where('tenant_id')` يدوي
 * في كود التطبيق. (ADR-020 · docs/22 بند ٦)
 *
 * @property mixed $value القيمة متخزّنة json — ممكن نص أو رقم أو منطقي أو مصفوفة
 */
final class TenantSetting extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<TenantSettingFactory> */
    use HasFactory;

    protected $table = 'tenant_settings';

    /** @var list<string> */
    protected $fillable = [
        'group',
        'key',
        'value',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            // json مش array: القيمة ممكن تبقى نص أو رقم أو منطقي أو مصفوفة
            'value' => 'json',
        ];
    }

    /** @return Factory<self> */
    protected static function newFactory(): Factory
    {
        return TenantSettingFactory::new();
    }
}
