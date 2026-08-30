# ٠٩ — نظام الإشعارات

## القنوات المطلوبة

| القناة | الاستخدام | الحالة |
|---|---|---|
| **قاعدة البيانات** | جرس الإشعارات في اللوحة | إلزامي |
| **البريد** | الإشعارات المهمة | إلزامي |
| **البث (Broadcast)** | تحديث فوري من غير refresh | إلزامي |
| **Toast** | ردود فعل فورية داخل الصفحة | إلزامي |
| **SMS / WhatsApp** | لاحقاً | نقطة توسّع جاهزة |

---

## ١. الإعداد

```bash
php artisan make:notifications-table
php artisan migrate
composer require laravel/reverb
php artisan reverb:install
```

في اللوحة:

```php
return $panel
    ->databaseNotifications()
    ->databaseNotificationsPolling('30s');
```

> لو Reverb شغّال، خلّي `->databaseNotificationsPolling(null)` واعتمد على البث — أخف بكتير على السيرفر.

---

## ٢. بنية الإشعار

الإشعارات في `Infrastructure` بتاعة السياق صاحب الحدث:

```php
namespace Src\Contexts\Identity\Infrastructure\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class UserInvitedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 60;

    public function __construct(
        public readonly int $userId,
        public readonly string $inviteUrl,
    ) {
        $this->afterCommit();       // ← إلزامي: مايتبعتش قبل ما الترانزاكشن تخلص
    }

    public function via(object $notifiable): array
    {
        return app(NotificationChannelResolver::class)
            ->for($notifiable, static::key());
    }

    public static function key(): string
    {
        return 'user_invited';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('identity.notifications.invited.subject'))
            ->greeting(__('common.greeting', ['name' => $notifiable->name]))
            ->line(__('identity.notifications.invited.body'))
            ->action(__('identity.notifications.invited.cta'), $this->inviteUrl)
            ->salutation(__('common.salutation'));
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('identity.notifications.invited.subject'))
            ->body(__('identity.notifications.invited.short'))
            ->icon('heroicon-o-user-plus')
            ->iconColor('success')
            ->actions([
                Action::make('view')
                    ->label(__('common.view'))
                    ->url($this->inviteUrl)
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }
}
```

### قواعد

1. **كل إشعار `implements ShouldQueue`** — مفيش إشعار متزامن.
2. **`$this->afterCommit()` في الكونستركتور** — بيمنع سباق شهير: الطابور بياخد الـ Job قبل ما الترانزاكشن تُحفظ، فالسجل مش موجود.
3. **`static::key()` إلزامي** — بيربط الإشعار بجدول التفضيلات.
4. **كل النصوص من `__()`** — والترجمة بتحصل تلقائياً حسب لغة المستقبِل (البند ٤).

---

## ٣. تفضيلات المستخدم

Laravel مفيهوش نظام تفضيلات جاهز — بنبنيه:

```php
Schema::create('notification_preferences', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
    $table->string('notification_key');       // user_invited
    $table->json('channels');                 // ["database", "mail"]
    $table->boolean('enabled')->default(true);
    $table->timestamps();

    $table->unique(['user_id', 'tenant_id', 'notification_key']);
});
```

كتالوج الإشعارات في `config/notifications.php`:

```php
return [
    'catalog' => [
        'user_invited' => [
            'group'    => 'identity',
            'channels' => ['database', 'mail'],       // المتاح
            'default'  => ['database', 'mail'],       // الافتراضي
            'required' => ['database'],               // مايقدرش يوقفه
        ],
        'password_changed' => [
            'group'    => 'security',
            'channels' => ['database', 'mail'],
            'default'  => ['database', 'mail'],
            'required' => ['mail'],                   // أمني — إجباري
        ],
        'export_ready' => [
            'group'    => 'system',
            'channels' => ['database', 'broadcast', 'mail'],
            'default'  => ['database', 'broadcast'],
            'required' => [],
        ],
        'weekly_digest' => [
            'group'    => 'reports',
            'channels' => ['mail'],
            'default'  => [],
            'required' => [],
        ],
    ],
];
```

الحلّال:

```php
final class NotificationChannelResolver
{
    public function for(object $notifiable, string $key): array
    {
        $definition = config("notifications.catalog.{$key}");

        if ($definition === null) {
            report(new UnknownNotificationKeyException($key));
            return ['database'];
        }

        $preference = NotificationPreference::query()
            ->where('user_id', $notifiable->getKey())
            ->where('tenant_id', app(TenantContext::class)->id())
            ->where('notification_key', $key)
            ->first();

        if ($preference === null) {
            return $definition['default'];
        }

        if (! $preference->enabled) {
            return $definition['required'];       // الإجباري بيعدّي حتى لو مقفول
        }

        return array_values(array_unique([
            ...array_intersect($preference->channels, $definition['channels']),
            ...$definition['required'],
        ]));
    }
}
```

### صفحة التفضيلات

صفحة Filament بتعرض الكتالوج **مجمّع حسب `group`**، وكل صف فيه checkbox لكل قناة متاحة. القنوات الإجبارية بتظهر مفعّلة ومعطّلة مع tooltip يشرح ليه.

---

## ٤. الترجمة التلقائية

على موديل المستخدم:

```php
use Illuminate\Contracts\Translation\HasLocalePreference;

class User extends Authenticatable implements HasLocalePreference
{
    public function preferredLocale(): string
    {
        return $this->locale ?? app(GeneralSettings::class)->default_locale;
    }
}
```

كده Laravel بيلفّ استدعاء `toMail`/`toDatabase` في `App::setLocale()` تلقائياً. **مش محتاج** `->locale()` يدوي.

> **معيار قبول:** مستخدم لغته `en` بياخد الرسالة إنجليزي حتى لو اللي بعت كان شغّال بالعربي.

---

## ٥. إشعارات Filament الفورية (Toast)

للردود داخل الصفحة:

```php
Notification::make()
    ->success()
    ->title(__('common.saved'))
    ->body(__('identity.notifications.user_updated'))
    ->duration(4000)
    ->send();

// خطأ مستمر لحد ما يقفله
Notification::make()
    ->danger()
    ->title(__('common.error'))
    ->body($exception->getMessage())
    ->persistent()
    ->actions([
        Action::make('retry')->label(__('common.retry'))->button(),
    ])
    ->send();
```

من داخل Job أو Action (بره طلب Livewire):

```php
Notification::make()
    ->success()
    ->title(__('export.ready'))
    ->actions([Action::make('download')->url($url)->markAsRead()])
    ->sendToDatabase($user, isEventDispatched: true);   // ← البث الفوري
```

> `isEventDispatched: true` بيبعت حدث websocket فوراً — الجرس بيتحدّث من غير polling. لازمة للإشعارات الجاية من الطوابير.

---

## ٦. البث (Broadcast)

```php
// config/broadcasting.php → reverb

// routes/channels.php
Broadcast::channel('App.Models.User.{id}', fn (User $user, int $id) => $user->id === (int) $id);
```

على موديل المستخدم:

```php
public function receivesBroadcastNotificationsOn(): string
{
    return 'App.Models.User.' . $this->id;
}
```

> ⚠️ **أمان:** القناة خاصة (`private`) إلزامي. لو خلّيتها عامة، أي حد يقدر يسمع إشعارات أي حد.

---

## ٧. الأداء والموثوقية

- **طابور منفصل:** `->onQueue('notifications')` — عشان إشعار مايتأخرش وراء Job تقيل
- **إعادة المحاولة:** `$tries = 5` + `$backoff = [10, 60, 300, 900]`
- **الفشل:** `failed()` بتسجّل في activitylog وبتبعت تنبيه للأدمن
- **تنظيف:** أمر مجدول يمسح الإشعارات المقروءة الأقدم من ٩٠ يوم
- **تجميع (batching):** ٢٠ حدث في دقيقة = إشعار واحد مجمّع، مش ٢٠

```php
// أمر مجدول يومي
Notification::query()
    ->whereNotNull('read_at')
    ->where('read_at', '<', now()->subDays(90))
    ->delete();
```

---

## ٨. معايير القبول

- [ ] كل إشعار `ShouldQueue` + `afterCommit()`
- [ ] الجرس في التوب‑بار بيتحدّث فوراً بالبث من غير refresh
- [ ] صفحة تفضيلات شغّالة والقنوات الإجبارية معطّلة بصرياً ومحترمة برمجياً
- [ ] مستخدم لغته `en` بياخد الرسالة إنجليزي — اختبار Pest يثبت
- [ ] القناة خاصة ومحدش يقدر يسمع إشعارات غيره — اختبار Pest يثبت
- [ ] إشعارات الطابور بتوصل بـ `isEventDispatched: true`
- [ ] كتالوج `config/notifications.php` مغطّي كل الإشعارات — اختبار بيفشل لو إشعار مش في الكتالوج
- [ ] أمر تنظيف الإشعارات مجدول وشغّال
