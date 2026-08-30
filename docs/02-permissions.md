# ٠٢ — نظام الصلاحيات

## المبدأ

> **أي عنصر في الواجهة لازم يعدّي على فحص صلاحية عشان يظهر — وأي endpoint وراه محمي كمان.**

طبقتين دايماً:
1. **الإخفاء** (`->visible()` / `shouldRegisterNavigation()`) — عشان الـ UX.
2. **المنع** (Policy / `canAccess()` / `->authorize()`) — عشان الأمان.

الإخفاء لوحده **مش أمان**. `shouldRegisterNavigation()` بيخفي اللينك بس الـ URL لسه شغّال.

---

## ١. التثبيت والإعداد

```bash
composer require spatie/laravel-permission:^8.3
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

في `config/permission.php`:

```php
'teams' => true,                      // ← إلزامي عندنا: الأدوار مربوطة بالمستأجر
'team_foreign_key' => 'tenant_id',

'cache' => [
    'expiration_time' => \DateInterval::createFromDateString(
        env('PERMISSION_CACHE_TTL', '24 hours')
    ),
    'key' => 'spatie.permission.cache',
    'store' => 'redis',               // ← Redis وليس default
],
```

> ⚠️ `PERMISSION_CACHE_TTL` مش موجود افتراضياً في الباكدج — إحنا اللي بنضيفه في الكونفيج المنشور. متعتمدش عليه من غير ما تتأكد إنه اتكتب.

`teams => true` معناه إن نفس المستخدم ممكن يكون `admin` في مستأجر و`viewer` في مستأجر تاني. تفاصيل ضبط الـ team الحالي في `docs/03-multi-tenancy.md`.

---

## ٢. الصلاحيات من ملف كونفيج (المطلب الأساسي)

مش هنكتب صلاحيات بإيدينا في سيدر. الصلاحيات **تُعرَّف في كونفيج** وتتزامن بأمر.

`config/authorization.php`:

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | الحارس (Guard)
    |--------------------------------------------------------------------------
    */
    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | فاصل المفتاح
    |--------------------------------------------------------------------------
    | الشكل النهائي للصلاحية: {action}.{resource}  مثال: view_any.users
    */
    'separator' => '.',

    /*
    |--------------------------------------------------------------------------
    | الأفعال القياسية لكل مورد
    |--------------------------------------------------------------------------
    */
    'default_actions' => [
        'view_any', 'view', 'create', 'update', 'delete',
        'delete_any', 'restore', 'force_delete', 'export',
    ],

    /*
    |--------------------------------------------------------------------------
    | الموارد
    |--------------------------------------------------------------------------
    | group   : مجموعة العرض في شاشة الأدوار (مفتاح ترجمة)
    | actions : لو فاضية → default_actions. لو محددة → دي بس.
    | extra   : أفعال مخصصة إضافية
    */
    'resources' => [

        'users' => [
            'group'   => 'identity',
            'actions' => null,
            'extra'   => ['impersonate', 'reset_password', 'force_logout'],
        ],

        'roles' => [
            'group'   => 'identity',
            'actions' => ['view_any', 'view', 'create', 'update', 'delete'],
        ],

        'tenants' => [
            'group'   => 'tenancy',
            'actions' => null,
        ],

        'media' => [
            'group'   => 'content',
            'actions' => ['view_any', 'view', 'create', 'delete'],
            'extra'   => ['download', 'change_disk'],
        ],

        'settings' => [
            'group'   => 'system',
            'actions' => ['view_any', 'update'],
            'extra'   => ['manage_storage', 'manage_mail', 'manage_appearance'],
        ],

        'activity_logs' => [
            'group'   => 'system',
            'actions' => ['view_any', 'view'],
            'extra'   => ['prune'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | صلاحيات الصفحات المخصصة (مش مرتبطة بمورد)
    |--------------------------------------------------------------------------
    */
    'pages' => [
        // الدخول للوحة نفسها — بيتفحص في canAccessPanel().
        // ⚠️ من غير البند ده محدش غير super_admin يقدر يفتح اللوحة أصلاً.
        'access.panel.admin'    => 'system',

        'access.dashboard'      => 'system',
        'access.horizon'        => 'system',
        'access.pulse'          => 'system',
        'access.health'         => 'system',
        'access.log_viewer'     => 'system',
        'access.backups'        => 'system',

        // مجموعات التنقّل اللي ليها بوابة على مستوى المجموعة (docs/07 بند ٢)
        'access.system'         => 'system',
        'access.tenancy'        => 'tenancy',

        'access.onboarding_analytics' => 'system',
    ],

    /*
    |--------------------------------------------------------------------------
    | صلاحيات الودجتس
    |--------------------------------------------------------------------------
    */
    'widgets' => [
        'widget.stats_overview'   => 'dashboard',
        'widget.revenue_chart'    => 'dashboard',
        'widget.recent_activity'  => 'dashboard',
    ],

    /*
    |--------------------------------------------------------------------------
    | الأدوار الافتراضية
    |--------------------------------------------------------------------------
    | '*' = كل الصلاحيات. الأدوار دي بتتزامن مع كل تشغيل للأمر.
    */
    'roles' => [
        'super_admin' => ['*'],

        // ملاحظة: الشق الشمال دايماً فعل، واليمين دايماً مورد (ADR-001).
        // «كل أفعال مورد» = *.users — مش users.*
        'admin' => [
            '*.users', '*.roles', '*.media', '*.settings',
            'access.panel.admin', 'access.dashboard', 'access.health',
            'widget.*',
        ],

        'editor' => [
            'view_any.users', 'view.users',
            '*.media',
            'access.panel.admin', 'access.dashboard',
            'widget.stats_overview',
        ],

        'viewer' => [
            'view_any.*', 'view.*',
            'access.panel.admin', 'access.dashboard',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | الدور الخارق
    |--------------------------------------------------------------------------
    | بيتجاوز كل الفحوصات عبر Gate::before
    */
    'super_admin_role' => 'super_admin',

    /*
    |--------------------------------------------------------------------------
    | حماية من الحذف
    |--------------------------------------------------------------------------
    | أدوار ممنوع حذفها أو تعديل صلاحياتها من الواجهة
    */
    'protected_roles' => ['super_admin'],
];
```

### قواعد نمط الـ wildcard

> **القاعدة الواحدة:** اسم الصلاحية دايماً `{action}.{resource}`. الشق الشمال فعل، الشق اليمين مورد.
> النمط بيتبع نفس الترتيب — مفيش استثناء ومفيش تخمين. (`docs/21-decisions.md` → ADR-001)

| النمط | المعنى |
|---|---|
| `*.users` | كل الأفعال على مورد `users` |
| `view_any.*` | فعل `view_any` على كل الموارد |
| `*` | كل حاجة |
| `access.*` | كل صلاحيات الصفحات |
| `widget.*` | كل صلاحيات الودجتس |

> ⚠️ **`users.*` غلط.** لو قريتها بالقاعدة، معناها «الفعل `users` على أي مورد» — وده مالوش معنى.
> اللي إنت عايزه هو `*.users`. الشكل ده كان موجود في نسخة أقدم من الوثيقة وبقى **مرفوض**.

**استثناء موثّق:** `access.dashboard` و`widget.stats_overview` الشق اليمين فيهم مش مورد.
دول مش صلاحيات موارد أصلاً — ليهم كتالوج منفصل (`pages` و`widgets`) وGates منفصلة،
والبادئة `access.` / `widget.` هي اللي بتميّزهم.

### `expandPatterns()` — الخوارزمية

بعد ADR-001 بقت غبية ومحدّدة، وده المطلوب:

```php
// قسّم على أول فاصل، طابق كل شق على '*' أو على قيمة حرفية.
// مفيش تخمين هل ده فعل ولا مورد.
[$actionPattern, $resourcePattern] = explode('.', $pattern, 2);

$matches = fn (string $p, string $value) => $p === '*' || $p === $value;
```

---

## ٣. أمر المزامنة

`php artisan authorization:sync`

```php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class SyncAuthorizationCommand extends Command
{
    protected $signature = 'authorization:sync
                            {--prune : حذف الصلاحيات غير الموجودة في الكونفيج}
                            {--dry   : عرض التغييرات من غير تنفيذ}';

    protected $description = 'مزامنة الصلاحيات والأدوار من config/authorization.php';

    public function handle(PermissionBuilder $builder): int
    {
        $guard    = config('authorization.guard');
        $defined  = $builder->allPermissionNames();   // مصفوفة أسماء من الكونفيج
        $existing = Permission::where('guard_name', $guard)->pluck('name')->all();

        $toCreate = array_diff($defined, $existing);
        $toPrune  = array_diff($existing, $defined);

        $this->table(['العملية', 'العدد'], [
            ['إضافة', count($toCreate)],
            ['حذف محتمل', count($toPrune)],
        ]);

        if ($this->option('dry')) {
            return self::SUCCESS;
        }

        foreach ($toCreate as $name) {
            Permission::create(['name' => $name, 'guard_name' => $guard]);
        }

        if ($this->option('prune') && $toPrune !== []) {
            Permission::whereIn('name', $toPrune)->where('guard_name', $guard)->delete();
        }

        // الأدوار
        foreach (config('authorization.roles') as $roleName => $patterns) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => $guard]);
            $role->syncPermissions($builder->expandPatterns($patterns));
            $this->line("  ✔ الدور <info>{$roleName}</info> — {$role->permissions()->count()} صلاحية");
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info('تمت المزامنة ومسح الكاش.');

        return self::SUCCESS;
    }
}
```

> **مهم:** الأمر ده لازم يتنفّذ في سكربت النشر بعد `migrate`. حطه في `docs/14-production-https.md`.

### `PermissionBuilder` — الكلاس المساعد

مكانه: `src/Support/Infrastructure/Authorization/PermissionBuilder.php`

مسؤول عن:
- `allPermissionNames(): array` — يفكّ الكونفيج لأسماء نهائية
- `expandPatterns(array $patterns): array` — يفكّ `users.*` لكل الأسماء
- `groups(): array` — يرجّع الصلاحيات مجمّعة للعرض في شاشة الأدوار

---

## ٤. ترجمة أسماء الصلاحيات

`lang/ar/authorization.php`:

```php
return [
    'actions' => [
        'view_any'       => 'عرض القائمة',
        'view'           => 'عرض التفاصيل',
        'create'         => 'إضافة',
        'update'         => 'تعديل',
        'delete'         => 'حذف',
        'delete_any'     => 'حذف جماعي',
        'restore'        => 'استرجاع',
        'force_delete'   => 'حذف نهائي',
        'export'         => 'تصدير',
        'impersonate'    => 'انتحال الشخصية',
        'reset_password' => 'إعادة تعيين كلمة المرور',
        'force_logout'   => 'إنهاء الجلسات',
        'download'       => 'تنزيل',
        'change_disk'    => 'تغيير مكان التخزين',
        'prune'          => 'تنظيف السجلات',
    ],

    'resources' => [
        'users'         => 'المستخدمون',
        'roles'         => 'الأدوار',
        'tenants'       => 'المؤسسات',
        'media'         => 'الوسائط',
        'settings'      => 'الإعدادات',
        'activity_logs' => 'سجل النشاط',
    ],

    'groups' => [
        'identity'  => 'الهوية والوصول',
        'tenancy'   => 'المؤسسات',
        'content'   => 'المحتوى',
        'system'    => 'النظام',
        'dashboard' => 'لوحة المعلومات',
    ],

    'pages' => [
        'access.panel.admin' => 'الدخول للوحة التحكم',
        'access.dashboard'   => 'الدخول للوحة المعلومات',
        'access.horizon'     => 'الدخول لـ Horizon',
        'access.pulse'       => 'الدخول لـ Pulse',
        'access.health'      => 'صفحة صحة النظام',
        'access.log_viewer'  => 'عارض السجلات',
        'access.backups'     => 'النسخ الاحتياطي',
        'access.system'      => 'قسم النظام',
        'access.tenancy'     => 'قسم المؤسسات',
        'access.onboarding_analytics' => 'مقاييس الأونبوردنج',
    ],

    'widgets' => [
        'widget.stats_overview'  => 'ودجت الإحصائيات',
        'widget.revenue_chart'   => 'ودجت الإيرادات',
        'widget.recent_activity' => 'ودجت آخر النشاطات',
    ],

    'roles' => [
        'super_admin' => 'مدير عام',
        'admin'       => 'مدير',
        'editor'      => 'محرّر',
        'viewer'      => 'مشاهد',
    ],
];
```

نفس الملف بالإنجليزي في `lang/en/authorization.php`.

دالة العرض:

```php
function permission_label(string $permission): string
{
    // الصفحات والودجتس ليها كتالوج منفصل، ومفتاحها بيتبحث **كامل** مش مقسّم.
    // لازم يتفحصوا الأول: 'access.dashboard' فيه فاصل، فالتقسيم هيلاقي شقين
    // ويعدّي على فرع المورد ويرجّع مفاتيح ترجمة خام. (ADR-003)
    foreach (['pages', 'widgets'] as $catalog) {
        $key = "authorization.{$catalog}.{$permission}";

        if (Lang::has($key)) {
            return __($key);
        }
    }

    [$action, $resource] = array_pad(
        explode(config('authorization.separator'), $permission, 2),
        2,
        null,
    );

    if ($resource === null) {
        return $permission;      // مفيش ترجمة ومفيش فاصل — رجّع الاسم الخام بدل مفتاح مكسور
    }

    return __('authorization.actions.' . $action) . ' — ' . __('authorization.resources.' . $resource);
}
```

> **الباگ اللي اتصلح:** النسخة القديمة كانت بتعمل
> `explode('.', $permission, 2) + [1 => null]` وبعدين `if ($resource === null)`.
> لكن `explode('.', 'access.dashboard', 2)` بيرجّع **عنصرين**، فالـ `+ [1 => null]`
> عمرها ما بتشتغل و`$resource` عمره ما بيبقى `null` — يعني فرع الصفحات والودجتس **كود ميت**.
> النتيجة كانت إن كل صلاحيات الصفحات والودجتس بترجع `authorization.actions.access — authorization.resources.dashboard`،
> واختبار القبول في بند ٩ (`->not->toContain('authorization.')`) كان هيفشل عليها كلها.
> استخدمنا `Lang::has()` بدل مقارنة الناتج بالمفتاح — أوضح وبيشتغل صح مع الـ fallback locale.

> **معيار قبول:** كل صلاحية في الكونفيج لها ترجمة في `ar` و`en`. فيه اختبار Pest بيفشل لو ناقص واحدة.

---

## ٥. الدور الخارق و«قواعد السلامة»

الشكل البسيط ده **خطير**:

```php
// ❌ بيتخطى الـ Policy بالكامل — بما فيها قواعد الأعمال
Gate::before(fn ($user) => $user->hasRole('super_admin') ? true : null);
```

`Gate::before` لو رجّع `true` **مابينفّذش الـ Policy أصلاً**. يعني المدير العام يقدر يحذف نفسه، أو ينشر سجل ناقص، أو يكسر أي قاعدة سلامة.

الشكل الصحيح — بيتجاوز **الصلاحيات** بس مش **قواعد السلامة** — موثّق بالكامل في **`docs/19-policies.md` بند ٥**. اقراه قبل ما تكتب `Gate::before`.

---

## ٦. فحص كل عنصر في Filament

> ⚠️ **الطريقة الوحيدة المسموحة للتفويض في التطبيق ده هي Laravel Policies.**
> **`docs/19-policies.md` هو المرجع الكامل — اقراه.** الملخص هنا بس عشان الربط.

القاعدة: `hasPermissionTo()` و`hasRole()` مسموح ليهم في مكانين بس — الكلاس الأساسي `Policy`، وتعريفات الـ Gates. أي مكان تاني بيستخدم `can($ability, $model)`.

| ❌ ممنوع | ✅ مطلوب |
|---|---|
| `$user->can('publish.announcements')` | `$user->can('publish', $announcement)` |
| `->visible(fn () => $user->can('update.users'))` | `->authorize('update')` |
| `canViewAny()` بتفحص نص صلاحية | سيبها — Filament بينادي الـ Policy تلقائياً |

### الدخول للوحة

```php
class User extends Authenticatable implements FilamentUser, HasTenants
{
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && Gate::allows('access.panel.' . $panel->getId());
    }
}
```

> ⚠️ **الصلاحية دي لازم تكون في `config/authorization.php` تحت `pages`.** لو ناقصة، الـ Gate
> مش هيتعرّف، و`Gate::allows()` هترجّع `false`، و**محدش غير `super_admin` هيقدر يفتح اللوحة** —
> لأنه الوحيد اللي بيعدّي من `Gate::before`. البند `access.panel.admin` موجود في الكتالوج فوق،
> وموجود في الأدوار التلاتة. أي لوحة جديدة بتضيف `access.panel.{id}` بتاعها. (ADR-003)

### المورد

**متكتبش `canViewAny()` ولا `canCreate()` ولا `canEdit()`.** Filament بينادي الـ Policy لوحده. الاستثناء الوحيد التنقّل:

```php
public static function shouldRegisterNavigation(): bool
{
    return static::canViewAny();     // ← بتمرّ على الـ Policy، مش على نص صلاحية
}
```

> ⚠️ `shouldRegisterNavigation()` بتخفي اللينك بس — **مابتقفلش الـ URL**. الحماية الفعلية من الـ Policy.

### الصفحات والودجتس (مالهاش موديل)

بتتعرّف كـ Gates من الكونفيج (`docs/19-policies.md` بند ٧):

```php
final class HealthPage extends Page
{
    public static function canAccess(): bool
    {
        return Gate::allows('access.health');
    }
}

final class StatsOverviewWidget extends BaseWidget
{
    public static function canView(): bool
    {
        return Gate::allows('widget.stats_overview');
    }
}
```

### الأزرار والإجراءات

```php
// إجراء قياسي — بياخد الـ Policy تلقائياً
DeleteAction::make();

// إجراء مخصص — اسم القدرة، والسجل بيتبعت للـ Policy لوحده
Action::make('impersonate')
    ->authorize('impersonate')
    ->authorizationTooltip()          // ← بيعرض سبب الرفض من الـ Policy
    ->requiresConfirmation()
    ->action(fn (User $record) => app(ImpersonateAction::class)->handle($record));

// إجراء جماعي — فحص كل سجل على حدة، مش فحص شامل واحد
DeleteBulkAction::make()->authorizeIndividualRecords('delete');
```

### الحقول الحساسة

```php
// الشكل المعياري (ADR-009): `$record ?? Model::class` — مش `$record !== null &&`
TextInput::make('salary')
    ->visible(fn (?User $record) => auth()->user()->can('viewSalary', $record ?? User::class))
    ->saved(fn (?User $record) => auth()->user()->can('updateSalary', $record ?? User::class));
```

> ⚠️ `->disabled()` و`->visible(false)` **مش أمان** — القيمة بتوصل في الـ request. لازم منع حفظ فعلي.

### أعمدة الجدول و Blade

```php
TextColumn::make('email')
    ->visible(fn () => auth()->user()->can('viewEmail', User::class));
```

```blade
@can('update', $user)
    <x-fc.button wire:click="save">{{ __('common.save') }}</x-fc.button>
@endcan
```

### ممنوع نهائياً

```php
->skipAuthorization()      // ❌ بيعطّل كل فحوصات الـ Policy — رفض PR فوري
```

---

## ٧. الكاش على Redis

### إزاي بيشتغل

الباكدج بيخزّن **تعريفات** الأدوار والصلاحيات (مش ربطها بالمستخدمين) في مفتاح واحد: `spatie.permission.cache`.

- استدعاء `assignRole()` / `givePermissionTo()` → مسح تلقائي.
- تعديل مباشر بـ `DB::table('permissions')->...` → **مفيش مسح تلقائي**. امسح بإيدك:

```php
app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
// أو
php artisan permission:cache-reset
```

### مصائد لازم تعرفها

1. **Octane:** الـ `PermissionRegistrar` singleton بيفضل عايش بين الطلبات. لو شغّالين Octane، امسح الكاش في مستمع `RequestTerminated`.
2. **تعدد المستأجرين:** المزوّد بيقرأ `permission.cache.key` أثناء `boot` — **قبل** ميدلوير المستأجر ما يشتغل. يعني لو بتغيّر البادئة لكل مستأجر، هي متأخرة. الحل في `docs/03-multi-tenancy.md`.
3. **الطوابير:** الـ Job بياخد كونتينر جديد، فالكاش بيتقرا من Redis. طبيعي.
4. **الاختبارات:** حط `'store' => 'array'` في `phpunit.xml` عشان الاختبارات ما تتلوّثش من بعض.

### كاش إضافي على مستوى التطبيق

للحاجات التقيلة (زي شجرة الصلاحيات لعرضها في شاشة الأدوار):

```php
cache()->tags(['authorization', "tenant:{$tenantId}"])
    ->remember('permission-tree', now()->addHour(), fn () => $builder->groups());

// الإبطال عند تغيير أي صلاحية
cache()->tags(['authorization'])->flush();
```

> `tags()` بيشتغل مع Redis و Memcached بس — مش مع `file` أو `database`.

---

## ٨. شاشة إدارة الأدوار

استخدم `bezhansalleh/filament-shield` كنقطة بداية، **بس** الصلاحيات بتاعتنا جاية من `config/authorization.php` مش من مسح الموارد. يعني:

- عطّل التوليد التلقائي بتاع Shield (`permissions.generate => false`)
- استخدم شاشة Shield لعرض/ربط الأدوار بالصلاحيات بس
- خلّي `authorization:sync` هو المصدر الوحيد للحقيقة

لو Shield وقف في وشنا، اكتب `RoleResource` بإيدنا — مش صعب، والمكسب إننا مالكين الشاشة بالكامل. القرار ده يتاخد في أسبوع ١ بعد تجربة عملية.

الشاشة لازم تعرض الصلاحيات **مجمّعة** حسب `group` من الكونفيج، بأسماء مترجمة، مع checkbox «تحديد الكل» لكل مجموعة.

---

## ٩. الاختبارات (إلزامية)

```php
it('يمنع المشاهد من إنشاء مستخدم', function () {
    $viewer = User::factory()->create()->assignRole('viewer');

    actingAs($viewer)
        ->get(UserResource::getUrl('create'))
        ->assertForbidden();
});

it('يخفي زرار الحذف عن من لا يملك الصلاحية', function () {
    $editor = User::factory()->create()->assignRole('editor');
    $target = User::factory()->create();

    actingAs($editor);

    livewire(ListUsers::class)
        ->assertTableActionHidden('delete', $target);
});

it('كل صلاحية في الكونفيج لها ترجمة عربية وإنجليزية', function () {
    foreach (app(PermissionBuilder::class)->allPermissionNames() as $permission) {
        expect(permission_label($permission))
            ->not->toContain('authorization.')
            ->and(permission_label($permission))->not->toBeEmpty();
    }
});

// الاختبار القديم هنا كان `$super->can('any.random.permission')` — وده بالظبط
// نص الصلاحية الممنوع في docs/19. عدّى قبل كده لأن tests/ ماكانتش متفحوصة؛
// بعد ADR-010 بقت متفحوصة وكان هيفشّل الـ CI.
it('المدير العام يتجاوز الصلاحيات داخل مستأجره', function () {
    $tenant = Tenant::factory()->create();
    $super  = userWithRole('super_admin', $tenant);

    app(TenantContext::class)->set($tenant->id);
    $record = Announcement::factory()->draft()->create(['tenant_id' => $tenant->id]);

    expect($super->can('viewAny', Announcement::class))->toBeTrue()
        ->and($super->can('update', $record))->toBeTrue();
});

it('لا يتجاوز المدير العام حدود المستأجر', function () {
    [$a, $b] = Tenant::factory()->count(2)->create();
    $super = userWithRole('super_admin', $a);

    app(TenantContext::class)->set($b->id);
    $foreign = Announcement::factory()->create(['tenant_id' => $b->id]);

    app(TenantContext::class)->set($a->id);

    expect(Gate::forUser($super)->inspect('view', $foreign)->status())->toBe(404);
});
```

---

## ١٠. معايير القبول

- [ ] `config/authorization.php` هو **المصدر الوحيد** للصلاحيات — مفيش `Permission::create()` في أي سيدر
- [ ] `php artisan authorization:sync --dry` بيعرض التغييرات من غير تنفيذ
- [ ] كل صلاحية مترجمة `ar` + `en` — الاختبار بيثبت ده
- [ ] `permission.cache.store = redis` ومؤكد بـ `php artisan about`
- [ ] كل Resource له Policy — و`Gate::guessPolicyNamesUsing()` بيلاقيها تلقائياً
- [ ] صفر `hasPermissionTo()` / `hasRole()` بره طبقة التفويض — اختبار معماري بيثبت
- [ ] صفر `can('action.resource')` بنص صلاحية — اختبار معماري بيثبت
- [ ] كل Action مخصص عليه `->authorize()`
- [ ] كل إجراء جماعي عليه `->authorizeIndividualRecords()`
- [ ] كل حقل حساس عليه منع حفظ فعلي (`saved(false)`) مش `->disabled()` بس
- [ ] كل Widget له `canView()` عبر Gate · كل صفحة لها `canAccess()` عبر Gate
- [ ] `Gate::before` بيحترم قواعد السلامة (`docs/19-policies.md` بند ٥)
- [ ] صفر `skipAuthorization()`
- [ ] اختبار Pest بيثبت إن `viewer` مش قادر يعمل create/update/delete
