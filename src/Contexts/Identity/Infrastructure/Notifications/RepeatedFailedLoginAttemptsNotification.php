<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Infrastructure\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Src\Support\Application\Contracts\NotificationChannels;

/**
 * تنبيه محاولات دخول فاشلة متكررة على الحساب — بريد بس. (docs/12 بند ٥)
 *
 * ⚠️ نفس منطق `NewDeviceLoginNotification`/`PasswordChangedNotification` —
 *    بريد بس، من غير قناة `database`، عشان محدّش يلوّث عدّ إشعارات اللوحة.
 */
final class RepeatedFailedLoginAttemptsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(public readonly string $ipAddress)
    {
        $this->afterCommit();
        $this->onQueue(config('notifications.queue'));
    }

    public static function key(): string
    {
        return 'repeated_failed_login_attempts';
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return app(NotificationChannels::class)->for($notifiable, self::key());
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('identity::identity.notifications.repeated_failed_login_attempts.subject'))
            ->greeting(__('identity::identity.notifications.repeated_failed_login_attempts.greeting'))
            ->line(__('identity::identity.notifications.repeated_failed_login_attempts.body', [
                'ip' => $this->ipAddress,
            ]))
            ->line(__('identity::identity.notifications.repeated_failed_login_attempts.warning'));
    }
}
