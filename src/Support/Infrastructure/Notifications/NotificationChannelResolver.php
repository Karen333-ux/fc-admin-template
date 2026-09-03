<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Notifications;

use Src\Support\Application\Contracts\NotificationChannels;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Domain\Exceptions\UnknownNotificationKeyException;
use Src\Support\Domain\Models\NotificationPreference;

/**
 * بيحوّل (مستقبِل + مفتاح إشعار) لقائمة قنوات. (docs/09 بند ٣)
 *
 * ترتيب الحسم:
 *   ١. المفتاح مش في الكتالوج → `['database']` + تسجيل الخطأ (الإشعار مايضيعش)
 *   ٢. مفيش تفضيل محفوظ      → `default` بتاعة الكتالوج
 *   ٣. التفضيل مقفول          → `required` بس
 *   ٤. تفضيل محفوظ            → (المختار ∩ المتاح) ∪ `required`
 *
 * ⚠️ `required` بتتضاف في كل الحالات اللي فيها تفضيل. ده مش تفصيل: قناة زي
 *    بريد «اتغيّرت كلمة مرورك» أمنية، والمستخدم مايقدرش يقفلها على نفسه.
 */
final readonly class NotificationChannelResolver implements NotificationChannels
{
    public function __construct(private TenantContext $context) {}

    /** @return list<string> */
    public function for(object $notifiable, string $key): array
    {
        $definition = $this->definition($key);

        if ($definition === null) {
            report(UnknownNotificationKeyException::for($key));

            return ['database'];
        }

        $preference = $this->preferenceFor($notifiable, $key);

        if ($preference === null) {
            return $this->normalise($definition['default']);
        }

        if (! $preference->enabled) {
            return $this->normalise($definition['required']);
        }

        /** @var list<string> $chosen */
        $chosen = $preference->channels;

        return $this->normalise([
            // المختار **مقصور على المتاح**: قناة اتشالت من الكتالوج
            // مايصحّش تفضل شغالة لأن صف قديم لسه فاكرها
            ...array_intersect($chosen, $definition['channels']),
            ...$definition['required'],
        ]);
    }

    /**
     * التفضيل المطبَّق: تفضيل المستأجر الحالي أولاً، وإلا التفضيل العام.
     *
     * ⚠️ **الحد بين المستأجرين.** الاستعلام بيقيّد على `user_id` وعلى
     *    (`tenant_id` الحالي أو NULL) — يعني تفضيل مستأجر «أ» عمره ما
     *    بيتقرا وإحنا في مستأجر «ب». مفيش global scope تحت الجدول ده
     *    (`tenant_id` nullable)، فالتقييد **صريح هنا**.
     */
    private function preferenceFor(object $notifiable, string $key): ?NotificationPreference
    {
        if (! method_exists($notifiable, 'getKey')) {
            return null;
        }

        $tenantId = $this->context->id();

        return NotificationPreference::query()
            ->where('user_id', $notifiable->getKey())
            ->where('notification_key', $key)
            ->where(function ($query) use ($tenantId): void {
                $query->whereNull('tenant_id');

                if ($tenantId !== null) {
                    $query->orWhere('tenant_id', $tenantId);
                }
            })
            // الأخص بيغلب العام: صف المستأجر قبل الصف اللي tenant_id فيه NULL
            ->orderByRaw('tenant_id IS NULL')
            ->first();
    }

    /**
     * @return array{group: string, channels: list<string>, default: list<string>, required: list<string>}|null
     */
    private function definition(string $key): ?array
    {
        /** @var array{group: string, channels: list<string>, default: list<string>, required: list<string>}|null $definition */
        $definition = config("notifications.catalog.{$key}");

        return $definition;
    }

    /**
     * @param  array<int, string>  $channels
     * @return list<string>
     */
    private function normalise(array $channels): array
    {
        return array_values(array_unique($channels));
    }
}
