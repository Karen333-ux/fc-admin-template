<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Infrastructure\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Src\Support\Application\Contracts\NotificationChannels;

/**
 * تنبيه تغيير كلمة المرور — إجباري بالبريد. (docs/12 بند ٤)
 *
 * ⚠️ بريد بس، نفس منطق `NewDeviceLoginNotification` (Slice 4.5) — تنبيه
 *    أمني على مستوى الحساب، من غير قناة `database` عشان محدّش يلوّث عدّ
 *    إشعارات اللوحة في اختبارات تانية. (config/notifications.php)
 */
final class PasswordChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct()
    {
        $this->afterCommit();
        $this->onQueue(config('notifications.queue'));
    }

    public static function key(): string
    {
        return 'password_changed';
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return app(NotificationChannels::class)->for($notifiable, self::key());
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('identity::identity.notifications.password_changed.subject'))
            ->greeting(__('identity::identity.notifications.password_changed.greeting'))
            ->line(__('identity::identity.notifications.password_changed.body'))
            ->line(__('identity::identity.notifications.password_changed.warning'));
    }
}
