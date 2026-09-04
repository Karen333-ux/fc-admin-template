<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Infrastructure\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Src\Support\Application\Contracts\NotificationChannels;

/**
 * تنبيه دخول من جهاز جديد — بريد بس. (docs/12 بند ٢)
 *
 * ⚠️ بريد بس بالقصد، مفيش قناة `database` — النص بيقول «إشعار بريد»
 *    صراحةً، وقناة اللوحة كانت هتحط سطر «على مستوى الحساب» بيظهر في كل
 *    مستأجرات المستخدم كل دخول، وده بيلوّث عدّ الإشعارات في اختبارات تانية
 *    بتستخدم `Auth::login()` كإعداد بس. (config/notifications.php)
 */
final class NewDeviceLoginNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        public readonly ?string $platform,
        public readonly ?string $browser,
        public readonly string $ipAddress,
        public readonly Carbon $occurredAt,
    ) {
        $this->afterCommit();
        $this->onQueue(config('notifications.queue'));
    }

    public static function key(): string
    {
        return 'new_device_login';
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return app(NotificationChannels::class)->for($notifiable, self::key());
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('identity::identity.notifications.new_device.subject'))
            ->greeting(__('identity::identity.notifications.new_device.greeting'))
            ->line(__('identity::identity.notifications.new_device.body', [
                'device' => $this->deviceLabel(),
                'ip' => $this->ipAddress,
                'time' => $this->occurredAt->translatedFormat('j F Y، g:i A'),
            ]))
            ->line(__('identity::identity.notifications.new_device.warning'));
    }

    private function deviceLabel(): string
    {
        return match (true) {
            $this->browser !== null && $this->platform !== null => "{$this->browser} — {$this->platform}",
            $this->browser !== null => $this->browser,
            $this->platform !== null => $this->platform,
            default => __('identity::identity.notifications.new_device.unknown_device'),
        };
    }
}
