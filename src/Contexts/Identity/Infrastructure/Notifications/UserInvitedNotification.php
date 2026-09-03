<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Infrastructure\Notifications;

use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Src\Support\Application\Contracts\NotificationChannels;
use Src\Support\Infrastructure\Notifications\NotificationOwnership;

/**
 * دعوة مستخدم للمؤسسة. (docs/09 بند ٢)
 *
 * ⚠️ الكلاس في `Infrastructure` مش `Domain`: `toDatabase()` بيبني رسالة
 *    بـ `Filament\Notifications\Notification`، و`Domain` ممنوع يستورد
 *    كلاس إطار محسوس (ADR-012).
 */
final class UserInvitedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300, 900];

    /**
     * المستأجر وقت الإنشاء — **مش** وقت تنفيذ الـ Job.
     *
     * ⚠️ الخاصية دي بتتسلسل مع الـ Job. لازم تتقرا هنا لأن
     *    `Queue::before` بيمسح `TenantContext` قبل كل Job، فالسياق جوّه
     *    `toDatabase()` فاضي دايماً. (ADR-024 · CLAUDE.md: «الطوابير
     *    مابتحملش سياق مستأجر — مرّر tenantId صراحةً»)
     */
    public readonly ?int $tenantId;

    public function __construct(
        public readonly string $inviteUrl,
    ) {
        $this->tenantId = app(NotificationOwnership::class)->currentTenantId();

        // ⚠️ إلزامي: من غيرها الطابور ممكن ياخد الـ Job قبل ما الترانزاكشن
        //    تُحفظ، فالمستخدم اللي الإشعار بيتكلم عنه مش موجود لسه.
        $this->afterCommit();

        // طابور منفصل — إشعار مايستناش ورا Job تقيل (docs/09 بند ٧)
        $this->onQueue(config('notifications.queue'));
    }

    /** مفتاح الكتالوج — بيربط الإشعار بتفضيلات المستخدم (docs/09 بند ٢ قاعدة ٣) */
    public static function key(): string
    {
        return 'user_invited';
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return app(NotificationChannels::class)->for($notifiable, self::key());
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('identity::identity.notifications.invited.subject'))
            ->greeting(__('identity::identity.notifications.invited.greeting'))
            ->line(__('identity::identity.notifications.invited.body'))
            ->action(__('identity::identity.notifications.invited.cta'), $this->inviteUrl);
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $message = FilamentNotification::make()
            ->title(__('identity::identity.notifications.invited.subject'))
            ->body(__('identity::identity.notifications.invited.short'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->iconColor('success')
            ->actions([
                Action::make('view')
                    ->label(__('identity::identity.notifications.invited.cta'))
                    ->url($this->inviteUrl)
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();

        // ⚠️ ختم المستأجر — الجدول مافيهوش عمود، فالملكية بتتخزّن هنا
        //    والجرس بيفلتر بيها. (ADR-024)
        return [...$message, ...app(NotificationOwnership::class)->stamp($this->tenantId)];
    }
}
