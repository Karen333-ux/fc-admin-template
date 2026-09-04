<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * جهاز/جلسة اتسجّل بيها دخول. (docs/12 بند ٢)
 *
 * ⚠️ مفيش `BelongsToTenant` — الجهاز ملك الحساب مش المؤسسة. (نفس منطق
 *    `users` نفسها — ADR-002)
 */
final class UserDevice extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'session_id',
        'device_name',
        'platform',
        'browser',
        'ip_address',
        'country',
        'is_trusted',
        'last_active_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_trusted' => 'boolean',
            'last_active_at' => 'datetime',
        ];
    }
}
