<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Src\Support\Infrastructure\Persistence\Concerns\BelongsToTenant;

/**
 * موديل اختبار بس.
 *
 * الشريحة الأولى مافيهاش موديل أعمال تابع لمستأجر (docs/23 بند ٥)، ومن غير
 * موديل بيستخدم الـ trait، الآليتين اللي الأمان كله واقف عليهم — `TenantScope`
 * و`TenantBoundary` — مش هيتجرّبوا فعلياً. الفيكستشر ده بيجرّبهم من غير ما
 * يضيف موديل أعمال بره النطاق.
 */
final class TenantOwnedRecord extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_owned_records';

    /** @var list<string> */
    protected $fillable = ['title'];   // tenant_id مش هنا — الـ trait بيحطه
}
