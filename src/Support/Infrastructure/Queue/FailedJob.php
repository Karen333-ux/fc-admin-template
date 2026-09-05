<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Queue;

use Illuminate\Database\Eloquent\Model;

/**
 * جدول failed_jobs — نظامي زي users بالظبط: مالوش tenant_id ولا
 * BelongsToTenant لأنه بنية تحتية مشتركة مش بيانات مستأجر. (docs/13 بند ٥)
 *
 * ⚠️ مفيش `$fillable` — القراءة والحذف بس (عبر `queue:retry`)، مفيش
 *    إنشاء أو تعديل عبر الموديل ده. الافتراضي `$guarded = ['*']` كافي.
 */
final class FailedJob extends Model
{
    public $timestamps = false;

    /** @var string */
    protected $table = 'failed_jobs';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'failed_at' => 'datetime',
        ];
    }

    public function jobName(): string
    {
        $payload = json_decode($this->payload, true);

        return $payload['displayName'] ?? $payload['job'] ?? '?';
    }
}
