# ٠٧ — السايدبار والتنقّل المتقدّم

## المشكلة

Filament الافتراضي حلو لـ ١٠ موارد. عندنا هيبقى **٦٠+** مورد. من غير شغل إضافي، السايدبار بيبقى قائمة طويلة محدش بيلاقي فيها حاجة.

---

## ١. المبادئ

1. **٧±٢** — أقصى عدد مجموعات ظاهرة. أكتر من كده الدماغ بتتوه.
2. **الوصول في ٣ نقرات** — أي شاشة على بُعد ٣ نقرات كحد أقصى.
3. **البحث أسرع من التصفّح** — للمستخدم المحترف، `Ctrl+K` هو الطريق الأساسي.
4. **الحالة بتفضل** — لو طويت مجموعة، تفضل مطوية بعد refresh.
5. **مفيش عنصر ميت** — أي عنصر ظاهر، المستخدم يقدر يدخله فعلاً.

---

## ٢. المجموعات

عرّفها كـ Enum مش strings — عشان الترتيب والأيقونات والترجمة في مكان واحد:

```php
namespace Src\Support\Presentation\Filament\Navigation;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel, HasIcon
{
    case Dashboard   = 'dashboard';
    case Identity    = 'identity';
    case Content     = 'content';
    case Operations  = 'operations';
    case Reports     = 'reports';
    case Tenancy     = 'tenancy';
    case System      = 'system';

    public function getLabel(): string
    {
        return __("navigation.groups.{$this->value}");
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Dashboard  => 'heroicon-o-home',
            self::Identity   => 'heroicon-o-users',
            self::Content    => 'heroicon-o-document-text',
            self::Operations => 'heroicon-o-cog-6-tooth',
            self::Reports    => 'heroicon-o-chart-bar',
            self::Tenancy    => 'heroicon-o-building-office-2',
            self::System     => 'heroicon-o-server-stack',
        };
    }

    public function sort(): int
    {
        return match ($this) {
            self::Dashboard  => 0,
            self::Identity   => 10,
            self::Content    => 20,
            self::Operations => 30,
            self::Reports    => 40,
            self::Tenancy    => 80,
            self::System     => 90,
        };
    }

    /** القدرة المطلوبة لظهور المجموعة كلها (Gate معرّف، مش نص صلاحية) */
    public function ability(): ?string
    {
        return match ($this) {
            self::System  => 'access.system',
            self::Tenancy => 'access.tenancy',
            default       => null,
        };
    }
}
```

التسجيل في اللوحة:

```php
->navigationGroups(
    collect(NavigationGroup::cases())
        ->filter(fn (NavigationGroup $g) => $g->ability() === null
            || Gate::allows($g->ability()))
        ->sortBy(fn (NavigationGroup $g) => $g->sort())
        ->map(fn (NavigationGroup $g) => \Filament\Navigation\NavigationGroup::make()
            ->label($g->getLabel())
            ->icon($g->getIcon())
            ->collapsible(true))
        ->all(),
)
```

في المورد:

```php
protected static ?int $navigationSort = 10;

public static function getNavigationGroup(): ?string
{
    return NavigationGroup::Identity->getLabel();
}
```

---

## ٣. إعدادات السايدبار

```php
return $panel
    ->sidebarCollapsibleOnDesktop()                    // زرار طيّ على الديسكتوب
    ->sidebarFullyCollapsibleOnDesktop(false)          // بنسيب الأيقونات ظاهرة لما نطوي
    ->collapsibleNavigationGroups(true)
    ->sidebarWidth('17rem')
    ->collapsedSidebarWidth('4.5rem')
    ->maxContentWidth(Width::Full)
    ->globalSearch()                                    // Ctrl+K
    ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
    ->globalSearchFieldSuffix(fn () => Platform::detect() === Platform::Mac ? '⌘K' : 'Ctrl+K')
    ->globalSearchDebounce('400ms')
    ->unsavedChangesAlerts()
    ->spa();                                            // تنقّل SPA — سريع جداً
```

> `->spa()` بيخلّي التنقّل من غير reload كامل. مصيدة: أي سكربت خارجي بيتحمّل في `HEAD` مش هيعيد التنفيذ. لو حصلت مشكلة، استخدم `->spa(hasPrefetching: true)` أو استثنِ صفحات معيّنة.

---

## ٤. البادچات الحيّة (Badges)

المؤشرات دي أهم من أي حاجة تانية في تطبيق كبير — بتقول للمستخدم فين الشغل.

```php
public static function getNavigationBadge(): ?string
{
    return Cache::tags(['nav-badges', 'tenant:' . app(TenantContext::class)->id()])
        ->remember(
            'nav:pending-users',
            now()->addMinutes(2),
            fn () => static::getModel()::query()->where('status', UserStatus::Pending)->count() ?: null,
        );
}

public static function getNavigationBadgeColor(): ?string
{
    return static::getNavigationBadge() > 10 ? 'danger' : 'warning';
}

public static function getNavigationBadgeTooltip(): ?string
{
    return __('identity.badges.pending_users');
}
```

> ⚠️ **مصيدة أداء:** البادچ بيتحسب في **كل** طلب لكل مورد ظاهر في السايدبار. ٢٠ مورد = ٢٠ استعلام في كل صفحة. **الكاش إلزامي** — مش اختياري. امسحه في مستمعي الأحداث المناسبة.

---

## ٥. البحث الشامل (Ctrl+K)

ده أهم من السايدبار نفسه للمستخدم المحترف.

```php
class UserResource extends Resource
{
    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            __('identity.fields.email') => $record->email,
            __('identity.fields.role')  => $record->roles->pluck('name')->join('، '),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return static::getUrl('edit', ['record' => $record]);
    }

    public static function getGlobalSearchResultActions(Model $record): array
    {
        return [
            Action::make('edit')
                ->url(static::getUrl('edit', ['record' => $record]))
                ->authorize('update'),      // ← السجل بيتبعت للـ Policy تلقائياً
        ];
    }

    /** تجنّب N+1 في نتائج البحث */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['roles']);
    }
}
```

> **معيار قبول:** كل مورد رئيسي قابل للبحث الشامل، وبحث بـ ٣ حروف بيرجّع نتيجة في أقل من ٣٠٠ms على ٥٠ ألف صف. الفهرسة مطلوبة.

---

## ٦. الأخيرة والمفضّلة (تحسين UX إضافي)

لتطبيق ٦٠ مورد، ده بيفرق جداً:

- **آخر ٥ شاشات زرتها** — يتخزّن في session، يظهر في أعلى السايدبار
- **المفضّلة** — المستخدم يثبّت شاشات، تتخزّن في `user_preferences`

```php
->navigationItems([
    ...app(RecentPagesResolver::class)->items(),
    ...app(PinnedPagesResolver::class)->items(),
])
```

نفّذها كـ render hook في `PanelsRenderHook::SIDEBAR_NAV_START` مع مكوّن Livewire خفيف.

---

## ٧. التنقّل المخصص بالكامل

لو احتجنا تحكم كامل:

```php
->navigation(function (NavigationBuilder $builder): NavigationBuilder {
    return $builder
        ->groups([
            NavigationGroup::make(__('navigation.groups.dashboard'))
                ->items([
                    NavigationItem::make(__('navigation.items.overview'))
                        ->icon('heroicon-o-home')
                        ->url(fn () => Dashboard::getUrl())
                        ->isActiveWhen(fn () => request()->routeIs('filament.admin.pages.dashboard'))
                        ->visible(fn () => Gate::allows('access.dashboard')),
                ]),
            // ...
        ]);
})
```

> استخدم ده **بحذر** — بتخسر الاكتشاف التلقائي. الأفضل نفضل على `discoverResources` + مجموعات مرتّبة.

---

## ٨. قائمة المستخدم والتبديلات

```php
->userMenuItems([
    'profile' => Action::make('profile')
        ->label(fn () => auth()->user()->name)
        ->url(fn () => EditProfile::getUrl())
        ->icon('heroicon-o-user-circle'),

    Action::make('devices')
        ->label(__('identity.menu.devices'))
        ->url(fn () => MyDevices::getUrl())
        ->icon('heroicon-o-device-phone-mobile'),

    Action::make('locale')
        ->label(fn () => app()->getLocale() === 'ar' ? 'English' : 'العربية')
        ->icon('heroicon-o-language')
        ->action(fn () => app(SwitchLocaleAction::class)->handle()),

    'logout' => Action::make('logout')->label(__('common.logout')),
])
```

مبدّل اللغة والثيم كمان في التوب‑بار عبر render hook:

```php
FilamentView::registerRenderHook(
    PanelsRenderHook::TOPBAR_END,
    fn (): string => Blade::render('@livewire(\'fc.locale-switcher\')'),
);
```

---

## ٩. render hooks المفيدة

| الثابت | الاستخدام عندنا |
|---|---|
| `PanelsRenderHook::FOOTER` | الفوتر والحقوق |
| `PanelsRenderHook::HEAD_END` | متغيّرات لون العلامة |
| `PanelsRenderHook::SIDEBAR_NAV_START` | الأخيرة والمفضّلة |
| `PanelsRenderHook::SIDEBAR_FOOTER` | مؤشر البيئة (staging/prod) ورقم الإصدار |
| `PanelsRenderHook::TOPBAR_END` | مبدّل اللغة والثيم |
| `PanelsRenderHook::BODY_START` | شريط الانتحال (impersonation banner) |
| `PanelsRenderHook::TOPBAR_AFTER` | تنبيه وضع الصيانة |

> فيه ٧٠+ ثابت في `Filament\View\PanelsRenderHook`. لو محتاج واحد مش هنا، افتح الكلاس في `vendor/` وشوف المتاح — متخمّنش.

---

## ١٠. معايير القبول

- [ ] عدد المجموعات الظاهرة ≤ ٧ لأي دور
- [ ] كل مجموعة قابلة للطيّ والحالة بتفضل بعد refresh
- [ ] مفيش عنصر ظاهر المستخدم مش قادر يدخله (اختبار آلي بيمرّ على كل لينك)
- [ ] `Ctrl+K` شغّال على كل الموارد الرئيسية وأسرع من ٣٠٠ms
- [ ] البادچات كلها مكاشة — Telescope بيعرض ≤ ٣ استعلامات لبناء السايدبار
- [ ] السايدبار مطوي بيسيب الأيقونات ظاهرة مع tooltips
- [ ] كل شاشة على بُعد ≤ ٣ نقرات
- [ ] السايدبار شغّال RTL و LTR بنفس الجودة
- [ ] على موبايل: السايدبار drawer وبيتقفل بعد الاختيار
