<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * هاش كلمة مرور قديمة، عشان منع إعادة الاستخدام. (docs/12 بند ٤)
 *
 * ⚠️ مفيش `BelongsToTenant` — نفس منطق `UserDevice` (ADR-002).
 */
final class PasswordHistory extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'password_hash',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password_hash',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
