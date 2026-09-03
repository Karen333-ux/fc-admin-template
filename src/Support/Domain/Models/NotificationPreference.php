<?php

declare(strict_types=1);

namespace Src\Support\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * تفضيل قنوات إشعار واحد لمستخدم واحد. (docs/09 بند ٣)
 *
 * ⚠️ **مابيستخدمش `BelongsToTenant`** بالقصد: الـ trait بيرمي
 * `MissingTenantContextException` لما مفيش سياق، وهنا `tenant_id = null`
 * قيمة **صالحة** معناها «تفضيل عام للمستخدم». التنطيق صريح في
 * `NotificationChannelResolver` بدل ما يبقى ضمني في global scope.
 *
 * ⚠️ `tenant_id` بره `$fillable`: مايتحطش من حمولة طلب أبداً — نفس قاعدة
 * `BelongsToTenant`. الحلّال بيحطه صراحةً.
 *
 * @property int $user_id
 * @property int|null $tenant_id المستأجر، أو null لتفضيل عام
 * @property string $notification_key
 * @property list<string> $channels القنوات المختارة — متخزّنة json
 * @property bool $enabled
 */
final class NotificationPreference extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'notification_key',
        'channels',
        'enabled',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'enabled' => 'boolean',
        ];
    }
}
