# ٠١ — بنية DDD (إلزامي)

## الفكرة في جملة

بدل ما كل حاجة تتكدّس في `app/Models` و `app/Http`، بنقسّم التطبيق لـ **سياقات محدودة (Bounded Contexts)**، وكل سياق مقسّم لـ **٤ طبقات**. الهدف: تفتح فولدر واحد وتفهم ميزة كاملة من غير ما تلف على ١٢ مجلد.

## الشكل النهائي

```
src/
├── Support/                          # المشترك بين كل السياقات
│   ├── Domain/
│   │   ├── Authorization/           # Policy (abstract), Decision  ← طبقة التفويض
│   │   ├── ValueObjects/            # Email, PhoneNumber, Money, TenantId
│   │   ├── Events/                  # DomainEvent (abstract)
│   │   └── Exceptions/
│   ├── Application/
│   │   ├── Contracts/               # واجهات مشتركة (DiskResolver, Clock)
│   │   └── Concerns/
│   ├── Infrastructure/
│   │   ├── Authorization/           # InvariantRegistry, PermissionBuilder
│   │   ├── Persistence/             # BaseModel, TenantScope, Casts
│   │   ├── Filesystem/              # DiskResolver الفعلي
│   │   └── Logging/
│   └── Presentation/
│       └── Filament/
│           ├── Concerns/            # HasTenantScope, HasAuditableActions
│           ├── Components/          # مكونات مشتركة
│           └── BaseResource.php
│
├── Contexts/
│   ├── Identity/                     # المستخدمون، الأدوار، الجلسات، 2FA
│   │   ├── Domain/
│   │   │   ├── Models/User.php
│   │   │   ├── Models/Role.php
│   │   │   ├── Enums/UserStatus.php
│   │   │   ├── Events/UserInvited.php
│   │   │   └── Repositories/UserRepository.php      ← واجهة (interface)
│   │   ├── Application/
│   │   │   ├── Actions/InviteUserAction.php
│   │   │   ├── Actions/EnableTwoFactorAction.php
│   │   │   ├── DataObjects/InviteUserData.php
│   │   │   └── Queries/ListActiveUsersQuery.php
│   │   ├── Infrastructure/
│   │   │   ├── Repositories/EloquentUserRepository.php
│   │   │   ├── Notifications/UserInvitedNotification.php
│   │   │   └── Listeners/
│   │   ├── Presentation/
│   │   │   └── Filament/
│   │   │       ├── Resources/UserResource.php
│   │   │       ├── Resources/UserResource/Pages/
│   │   │       ├── Pages/MyDevices.php
│   │   │       └── Widgets/ActiveUsersWidget.php
│   │   ├── Database/
│   │   │   ├── Migrations/
│   │   │   ├── Factories/
│   │   │   └── Seeders/
│   │   ├── Lang/{ar,en}/identity.php
│   │   ├── Routes/web.php
│   │   ├── Tests/
│   │   └── IdentityServiceProvider.php
│   │
│   ├── Tenancy/                      # المستأجرون والاشتراكات
│   ├── Settings/                     # الإعدادات الديناميكية
│   ├── Media/                        # الملفات والوسائط
│   ├── Notifications/                # الإشعارات والتفضيلات
│   └── Audit/                        # سجل النشاط والمراقبة
```

## قواعد الطبقات (احفظها)

| الطبقة | تحتوي | **ممنوع** تحتوي |
|---|---|---|
| **Domain** | موديلات، Enums، Value Objects، أحداث، **واجهات** المستودعات، قواعد الأعمال الصافية | Filament، Http، Facades، أي حاجة من Laravel غير Eloquent |
| **Application** | Actions (حالات الاستخدام)، DTOs، Queries، Handlers | Blade، Filament، `request()`، `auth()` مباشرة |
| **Infrastructure** | تنفيذ المستودعات، **Policies**، Notifications، Listeners، تكامل خارجي، Jobs | منطق أعمال بره الـ Policies |
| **Presentation** | Filament Resources/Pages/Widgets، Controllers، Blade | منطق أعمال، استعلامات معقدة |

**الاتجاه:** `Presentation → Application → Domain`. و`Infrastructure` بينفّذ واجهات `Domain`.
**Domain لا يعرف أي حد فوقه.** لو موديل في Domain بيـ `use` حاجة من Filament — ده باگ.

## اتجاه الاعتماد بين السياقات

سياق **ما يعرفش** الموديلات الداخلية لسياق تاني. التواصل بطريقتين بس:

1. **أحداث الدومين (المفضّل):** `Identity` بيطلق `UserInvited`، و`Notifications` بيسمع.
2. **واجهة عامة (Public API):** كلاس واحد في `Application/` يستدعيه التانيين.

```php
// ❌ غلط — Media بيستورد موديل من Identity
use Src\Contexts\Identity\Domain\Models\User;

// ✅ صح — Media بيسمع حدث
class ProvisionUserAvatarListener {
    public function handle(UserInvited $event): void { /* $event->userId */ }
}
```

## الـ Autoload

في `composer.json`:

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "app/",
      "Src\\": "src/",
      "Database\\Factories\\": "database/factories/",
      "Database\\Seeders\\": "database/seeders/"
    }
  }
}
```

بعد أي سياق جديد: `composer dump-autoload`.

## ServiceProvider لكل سياق

```php
// src/Contexts/Identity/IdentityServiceProvider.php
namespace Src\Contexts\Identity;

use Filament\Panel;
use Illuminate\Support\ServiceProvider;
use Src\Contexts\Identity\Domain\Repositories\UserRepository;
use Src\Contexts\Identity\Infrastructure\Repositories\EloquentUserRepository;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
        $this->loadTranslationsFrom(__DIR__ . '/Lang', 'identity');
        $this->loadRoutesFrom(__DIR__ . '/Routes/web.php');

        // تسجيل موارد Filament للسياق ده على لوحة admin فقط
        Panel::configureUsing(function (Panel $panel): void {
            if ($panel->getId() !== 'admin') {
                return;
            }

            $panel
                ->discoverResources(
                    in: __DIR__ . '/Presentation/Filament/Resources',
                    for: 'Src\\Contexts\\Identity\\Presentation\\Filament\\Resources',
                )
                ->discoverPages(
                    in: __DIR__ . '/Presentation/Filament/Pages',
                    for: 'Src\\Contexts\\Identity\\Presentation\\Filament\\Pages',
                )
                ->discoverWidgets(
                    in: __DIR__ . '/Presentation/Filament/Widgets',
                    for: 'Src\\Contexts\\Identity\\Presentation\\Filament\\Widgets',
                );
        });
    }
}
```

سجّل المزوّدات في `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    Src\Contexts\Identity\IdentityServiceProvider::class,
    Src\Contexts\Tenancy\TenancyServiceProvider::class,
    // ...
];
```

## نمط الـ Action (قلب طبقة Application)

كل حالة استخدام = كلاس واحد، دالة `handle` واحدة.

```php
namespace Src\Contexts\Identity\Application\Actions;

final readonly class InviteUserAction
{
    public function __construct(
        private UserRepository $users,
        private Dispatcher $events,
    ) {}

    public function handle(InviteUserData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = $this->users->create($data);
            $user->assignRole($data->role);

            $this->events->dispatch(new UserInvited($user->id, $data->tenantId));

            return $user;
        });
    }
}
```

الاستدعاء من Filament:

```php
CreateAction::make()
    ->using(fn (array $data) => app(InviteUserAction::class)->handle(InviteUserData::from($data)));
```

> **القاعدة:** لو Filament Resource بقى فيه أكتر من ٥ سطور منطق — انقله لـ Action.

### وين تحط قواعد «هل ينفع؟»

فيه نوعين منطق بيتخلطوا كتير:

| السؤال | المكان |
|---|---|
| «هل **ينفع** المستخدم ده يعمل كده على السجل ده؟» | **Policy** (`Infrastructure/Policies/`) |
| «طيب **اعمل** الحاجة» | **Action** (`Application/Actions/`) |

الـ Policy بتجمع الصلاحية وقواعد الأعمال المانعة في مكان واحد وبترجّع سبب الرفض. الـ Action بينفّذ، وبيستدعي `Gate::authorize()` كحاجز أخير عشان يشتغل بأمان من API أو Job.

**ليه الـ Policy في Infrastructure مش Domain؟** لأنها بتعتمد على `Illuminate\Auth\Access\Response` وعلى نظام الصلاحيات — دول تفاصيل بنية تحتية. قواعد الأعمال **الصافية** (اللي مالهاش علاقة بمين اللي بيعمل) تفضل في الموديل أو Value Object في `Domain/`.

التفاصيل الكاملة في `docs/19-policies.md`.

## متى تعمل سياق جديد؟

اعمل سياق جديد لما الميزة:
- ليها لغة أعمال خاصة بيها (مصطلحات مالكها بيستخدمها)
- ممكن نشيلها كلها من غير ما نكسر باقي التطبيق
- ليها أكتر من ٣ موديلات مترابطة

**متعملش سياق** لموديل واحد. حطه في السياق الأقرب.

## معايير القبول

- [ ] `src/Support` و`src/Contexts/Identity` موجودين بالبنية دي بالظبط
- [ ] `IdentityServiceProvider` بيسجّل موارد Filament من مساره الخاص
- [ ] اختبار معماري (Pest Arch) بيمنع `Domain` من استيراد `Filament` أو `Illuminate\Http`
- [ ] `UserResource` مفيهوش منطق أعمال — التنفيذ في Actions وقواعد «هل ينفع؟» في Policies
- [ ] `Src\Support\Domain\Authorization\{Policy,Decision}` موجودين
- [ ] `Gate::guessPolicyNamesUsing()` مضبوط ومفيش `Gate::policy()` يدوي

```php
// tests/Architecture/LayersTest.php
arch('Domain لا يعتمد على Filament أو HTTP')
    ->expect('Src\Contexts')
    ->toOnlyBeUsedIn('Src')
    ->and('Src')
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump']);

arch('طبقة Domain نظيفة')
    ->expect('Src\Contexts\Identity\Domain')
    ->not->toUse(['Filament', 'Illuminate\Http', 'Livewire']);

arch('كل Policy ترث الكلاس الأساسي')
    ->expect('Src\Contexts\Identity\Infrastructure\Policies')
    ->toExtend(Src\Support\Domain\Authorization\Policy::class);
```
