# ١٩ — طبقة السياسات (Policies) — المرجع الوحيد للتفويض

> **القاعدة الحاكمة:** مفيش أي مكان في التطبيق بيسأل «هل المستخدم معاه الصلاحية دي؟» غير جوه Policy أو Gate.
> كل باقي الكود بيسأل سؤال مختلف: **«هل المستخدم يقدر يعمل الحاجة دي على السجل ده؟»**

---

## ١. ليه؟ المشكلة اللي بنحلها

الطريقة السهلة (وهي غلط):

```php
// ❌ في الـ Resource
->visible(fn () => auth()->user()->can('publish.announcements'))
```

المشكلة إن ده بيجاوب على **نص الصلاحية بس**. لكن «هل ينفع أنشر؟» في الواقع مش سؤال صلاحية واحد — هو:

1. معاه صلاحية `publish.announcements`؟ **و**
2. الإعلان لسه مسودة (مش منشور قبل كده)؟ **و**
3. الإعلان مش محذوف؟ **و**
4. العنوان باللغة الاحتياطية متكتب؟ **و**
5. تاريخ الانتهاء لسه في المستقبل؟

البنود ٢–٥ دي **قواعد أعمال (business rules)**، مش صلاحيات. لو حطيتها في الـ Resource:

- هتتكرر في كل مكان الزرار فيه (الجدول، صفحة العرض، الـ API، الـ Job)
- الواجهة هتخفي الزرار لكن الـ Action نفسه لسه ينفّذ لو حد بعت الطلب مباشرة
- مش هتقدر تختبرها من غير ما تشغّل Livewire
- لما القاعدة تتغيّر، هتنسى مكان من الخمسة

**الحل:** القدرة (ability) هي **الصلاحية + قواعد الأعمال معاً**، في مكان واحد، قابل للاختبار لوحده، وبيرجّع **سبب الرفض** عشان المستخدم يفهم.

```php
// ✅ في الـ Resource — سؤال واحد، إجابة واحدة
->authorize('publish')
->authorizationTooltip()     // بيعرض سبب الرفض الجاي من الـ Policy
```

---

## ٢. القاعدة الحديدية

**`hasPermissionTo()` له مكانين بس** (ADR-010):

1. `Src\Support\Infrastructure\Authorization\Decision::permission()`
2. تعريفات الـ Gates في `AuthorizationServiceProvider`

**حتى جوه الـ Policies هو ممنوع** — `Decision::permission()` هي اللي بتعمله، ومفيش سبب تكرره.

**`hasRole()` / `hasAnyRole()` / `hasAllRoles()`** ليهم نفس المكانين، **زائد** حالة واحدة جوه
الـ Policy: الفحص على **الهدف** مش على الفاعل.

```php
// ✅ مسموح جوه Policy — الفحص على الهدف
->rule(! $target->hasRole('super_admin'), 'cannot_impersonate_super_admin')

// ❌ ممنوع في أي مكان — الفحص على الفاعل
if ($user->hasRole('admin')) { ... }
```

الفرق: «هل الفاعل مسموح له؟» ده سؤال صلاحية وبيتجاوب عليه `permission()`. «هل الهدف نوعه كذا؟»
ده سؤال **قاعدة أعمال** عن السجل، وبيتجاوب عليه `rule()`.

**في أي مكان تاني** — Resource، Page، Widget، Action، Blade، Livewire، Job، Command، Middleware — بتستخدم:

```php
$user->can($ability, $model)          // أو
Gate::inspect($ability, $model)       // لو عايز رسالة الرفض
```

> اختبار معماري بيرفض الـ PR لو الكلام ده اتكسر. البند ٩.

### الفرق بين الاتنين

| ❌ ممنوع | ✅ مطلوب |
|---|---|
| `$user->can('publish.announcements')` | `$user->can('publish', $announcement)` |
| `$user->hasPermissionTo('delete.users')` | `$user->can('delete', $user)` |
| `$user->hasRole('admin')` | `$user->can('viewAny', User::class)` |
| `->visible(fn () => auth()->user()->can('update.users'))` | `->authorize('update')` |

لاحظ: `'publish.announcements'` اسم **صلاحية**. `'publish'` اسم **قدرة** بتتحول لدالة `AnnouncementPolicy::publish()`.

---

## ٣. الكلاس الأساسي

> 📍 **المكان: `Src\Support\Infrastructure\Authorization\`** — مش `Domain`. (ADR-006)
>
> الكلاسين دول بيعتمدوا على `Illuminate\Auth\Access\Response` وعلى نظام الصلاحيات — دول
> تفاصيل بنية تحتية. ده **نفس** المنطق اللي `docs/01` بيستخدمه عشان يحطّ policies السياقات
> في `Infrastructure`. طبقة التفويض كلها بقت في مجلد واحد مع `InvariantRegistry`
> و`PermissionBuilder` و`TenantBoundary`.

`src/Support/Infrastructure/Authorization/Policy.php`:

```php
<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Authorization;

use Illuminate\Database\Eloquent\Model;

abstract class Policy
{
    /** اسم المورد كما هو في config/authorization.php */
    abstract protected function resource(): string;

    /**
     * قدرات لا يتجاوزها المدير العام.
     *
     * دي مش صلاحيات — دي قواعد سلامة. مثال: «مينفعش تحذف نفسك»
     * لازم تفضل شغالة حتى للـ super_admin، وإلا هيقفل على نفسه.
     *
     * ملاحظة: حدود المستأجر **مش** محتاجة تتسجّل هنا — بتتفرض تلقائياً
     * عبر decideFor() و Gate::before. (ADR-005 · ADR-007)
     *
     * @return list<string>
     */
    public function invariants(): array
    {
        return [];
    }

    public function isInvariant(string $ability): bool
    {
        return in_array($ability, $this->invariants(), true);
    }

    /**
     * سلسلة فحص من غير سجل — للقدرات اللي مالهاش موديل.
     * استخدمها في viewAny() و create() بس.
     */
    protected function decide(): Decision
    {
        return new Decision();
    }

    /**
     * سلسلة فحص على سجل. **حارس المستأجر بيتطبّق هنا قبل أي حاجة تانية.**
     *
     * أي دالة بتاخد موديل كمعامل تاني لازم تستخدم دي — مش decide().
     * كده نسيان الحارس بقى مستحيل بدل ما يبقى مكشوف. (ADR-007)
     */
    protected function decideFor(Model $record): Decision
    {
        return (new Decision())->withinTenant($record);
    }

    /** يبني اسم الصلاحية الكامل: publish + announcements → publish.announcements */
    public function permissionFor(string $action): string
    {
        return $action . config('authorization.separator') . $this->resource();
    }
}
```

`src/Support/Infrastructure/Authorization/Decision.php`:

```php
<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Authorization;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Src\Support\Infrastructure\Tenancy\TenantBoundary;

/**
 * سلسلة فحص تفويض. بتقف عند أول رفض وبترجّع سببه.
 * الترتيب مقصود: حد المستأجر، بعدين الصلاحية، بعدين قواعد الأعمال.
 */
final class Decision
{
    private ?Response $denial = null;

    /**
     * حارس المستأجر (ADR-005). بيتنادى تلقائياً من Policy::decideFor()
     * — متنادهاش بإيدك.
     *
     * 404 مش 403: «ممنوع» بتأكد إن السجل موجود، وده تسريب في حد ذاته.
     * الموديلات المش تابعة لمستأجر (Tenant, User — ADR-002) بتعدّي من غير فحص.
     */
    public function withinTenant(Model $record): self
    {
        if ($this->denial !== null || ! app(TenantBoundary::class)->crosses($record)) {
            return $this;
        }

        $this->denial = Response::denyAsNotFound(
            __('authorization.denied.record_not_found'),
        );

        return $this;
    }

    /** الفحص التاني دايماً: هل معاه الصلاحية أصلاً؟ */
    public function permission(
        Authenticatable $user,
        Policy $policy,
        string $action,
    ): self {
        if ($this->denial !== null) {
            return $this;
        }

        $permission = $policy->permissionFor($action);

        // config() مش filament(): الـ Policy بتتنادى من Job و Command و API
        // و Test — ومفيش لوحة Filament مبنية في الحالات دي. (ADR-006)
        if (! $user->hasPermissionTo($permission, config('authorization.guard'))) {
            $this->denial = Response::deny(
                __('authorization.denied.missing_permission', [
                    'permission' => permission_label($permission),
                ]),
            );
        }

        return $this;
    }

    /** قاعدة أعمال. الرسالة بتوصل للمستخدم فعلاً — خليها مفيدة. */
    public function rule(bool $passes, string $messageKey, array $replace = []): self
    {
        if ($this->denial !== null || $passes) {
            return $this;
        }

        $this->denial = Response::deny(__("authorization.denied.{$messageKey}", $replace));

        return $this;
    }

    /** نفس rule() بس بتقييم كسول — للفحوصات اللي فيها استعلام */
    public function ruleUsing(callable $passes, string $messageKey, array $replace = []): self
    {
        if ($this->denial !== null) {
            return $this;
        }

        return $this->rule((bool) $passes(), $messageKey, $replace);
    }

    /**
     * رفض بإخفاء وجود السجل (404 بدل 403).
     * استخدمها لما مجرد معرفة إن السجل موجود يعتبر تسريب.
     */
    public function ruleOrNotFound(bool $passes, string $messageKey): self
    {
        if ($this->denial !== null || $passes) {
            return $this;
        }

        $this->denial = Response::denyAsNotFound(__("authorization.denied.{$messageKey}"));

        return $this;
    }

    public function response(): Response
    {
        return $this->denial ?? Response::allow();
    }
}
```

> **ليه `Response` مش `bool`؟** لأن `bool` بيضيّع سبب الرفض. `Response::deny('السبب')` بيخلّي Filament يعرض للمستخدم **ليه** الزرار معطّل بدل ما يختفي بصمت. ده فرق ضخم في تجربة الاستخدام وفي وقت الدعم الفني.

---

## ٤. سياسة نموذجية

```php
<?php

declare(strict_types=1);

namespace Src\Contexts\Content\Infrastructure\Policies;

use Illuminate\Auth\Access\Response;
use Src\Contexts\Content\Domain\Enums\AnnouncementStatus;
use Src\Contexts\Content\Domain\Models\Announcement;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Support\Infrastructure\Authorization\Policy;

final class AnnouncementPolicy extends Policy
{
    protected function resource(): string
    {
        return 'announcements';
    }

    /** حتى المدير العام لازم يعدّي على قواعد السلامة دي */
    public function invariants(): array
    {
        return ['publish', 'delete'];
    }

    // ══════════════ قدرات بدون سجل ══════════════

    public function viewAny(User $user): Response
    {
        return $this->decide()
            ->permission($user, $this, 'view_any')
            ->response();
    }

    public function create(User $user): Response
    {
        return $this->decide()
            ->permission($user, $this, 'create')
            ->rule(
                ! app(GeneralSettings::class)->maintenance_mode,
                'maintenance_mode',
            )
            ->response();
    }

    // ══════════════ قدرات على سجل ══════════════

    public function view(User $user, Announcement $announcement): Response
    {
        // decideFor() طبّق حارس المستأجر خلاص — مفيش ruleOrNotFound يدوي
        return $this->decideFor($announcement)
            ->permission($user, $this, 'view')
            ->response();
    }

    public function update(User $user, Announcement $announcement): Response
    {
        return $this->decideFor($announcement)
            ->permission($user, $this, 'update')
            ->rule(! $announcement->trashed(), 'record_trashed')
            // إعلان منشور مايتعدّلش إلا بصلاحية النشر
            ->ruleUsing(
                fn () => $announcement->status !== AnnouncementStatus::Published
                    || $user->can('publish', $announcement),
                'announcement.published_needs_publish_permission',
            )
            ->response();
    }

    public function delete(User $user, Announcement $announcement): Response
    {
        return $this->decideFor($announcement)
            ->permission($user, $this, 'delete')
            ->rule(! $announcement->trashed(), 'record_trashed')
            // ← قاعدة سلامة: إعلان منشور وواصل للناس مايتحذفش، يتأرشف
            ->rule(
                $announcement->status !== AnnouncementStatus::Published,
                'announcement.published_must_archive',
            )
            ->response();
    }

    public function publish(User $user, Announcement $announcement): Response
    {
        return $this->decideFor($announcement)
            ->permission($user, $this, 'publish')
            ->rule(! $announcement->trashed(), 'record_trashed')
            ->rule(
                $announcement->status === AnnouncementStatus::Draft,
                'announcement.not_draft',
            )
            ->rule(
                filled($announcement->getTranslation('title', config('app.fallback_locale'), false)),
                'announcement.missing_fallback_title',
                ['locale' => config('app.fallback_locale')],
            )
            ->rule(
                $announcement->expires_at === null || $announcement->expires_at->isFuture(),
                'announcement.already_expired',
            )
            ->response();
    }

    public function pin(User $user, Announcement $announcement): Response
    {
        return $this->decideFor($announcement)
            ->permission($user, $this, 'pin')
            ->rule(
                $announcement->status === AnnouncementStatus::Published,
                'announcement.pin_requires_published',
            )
            ->ruleUsing(
                fn () => $announcement->is_pinned
                    || Announcement::where('is_pinned', true)->count() < config('content.max_pinned', 3),
                'announcement.pin_limit_reached',
                ['limit' => config('content.max_pinned', 3)],
            )
            ->response();
    }
}
```

اقرا أي دالة فيهم بصوت عالي — بتقرا زي جملة عربية. ده المطلوب.

---

## ٥. المدير العام وقواعد السلامة (نقطة حرجة)

`Gate::before` لو رجّع `true` **بيتخطى الـ Policy بالكامل** — بما فيها قواعد الأعمال. يعني الشكل البسيط ده:

```php
// ❌ خطير
Gate::before(fn ($user) => $user->hasRole('super_admin') ? true : null);
```

معناه إن المدير العام يقدر:
- يحذف نفسه ويقفل على نفسه بره النظام
- ينشر إعلان ناقص العنوان
- ينتحل شخصية مدير عام تاني
- يحذف آخر مستأجر نشط

دي مش صلاحيات زايدة — دي **باگات**. الصلاحيات بتقول «مسموح ليك»، وقواعد السلامة بتقول «الحاجة دي مستحيلة منطقياً». المدير العام بيتخطى الأولى بس.

### الحل

`src/Support/Infrastructure/Authorization/InvariantRegistry.php`:

```php
final class InvariantRegistry
{
    /** هل القدرة دي محمية بقاعدة سلامة على الموديل ده؟ */
    public function guards(string $ability, mixed $argument): bool
    {
        if ($argument === null) {
            return false;
        }

        $policy = Gate::getPolicyFor($argument);

        return $policy instanceof Policy && $policy->isInvariant($ability);
    }
}
```

في `AuthorizationServiceProvider::boot()`:

```php
Gate::before(function (Authenticatable $user, string $ability, array $arguments = []): ?bool {
    if (! $user->hasRole(config('authorization.super_admin_role'))) {
        return null;   // ← null مش false، عشان الـ Policy تكمّل
    }

    $argument = $arguments[0] ?? null;

    // ١. قواعد السلامة — المدير العام مابيتخطاهاش.
    //    القدرات المحمية بتكمّل للـ Policy عشان تتفحص قواعدها،
    //    والـ permission() جواها هتعدّي عادي لأنه معاه كل الصلاحيات.
    if (app(InvariantRegistry::class)->guards($ability, $argument)) {
        return null;
    }

    // ٢. حدود المستأجر — المدير العام مابيتخطاهاش كمان (ADR-005).
    //    بنرجّع null عشان الـ Policy تشتغل وقاعدة ruleOrNotFound جواها
    //    ترجّع 404 مش 403 — مانأكدش إن السجل موجود أصلاً.
    if (app(TenantBoundary::class)->crosses($argument)) {
        return null;
    }

    return true;      // تجاوز الصلاحيات بس — جوه المستأجر الحالي
});
```

`src/Support/Infrastructure/Tenancy/TenantBoundary.php`:

```php
final class TenantBoundary
{
    /** هل السجل ده تابع لمستأجر غير المستأجر الحالي؟ */
    public function crosses(mixed $argument): bool
    {
        // اسم كلاس أو null — مفيش سجل نقارن بيه (viewAny / create)
        if (! $argument instanceof Model) {
            return false;
        }

        // موديل مش تابع لمستأجر (Tenant, User) — مالوش حد نعديه
        if (! in_array(BelongsToTenant::class, class_uses_recursive($argument), true)) {
            return false;
        }

        $current = app(TenantContext::class)->id();

        return $current !== null
            && $argument->getAttribute('tenant_id') !== $current;
    }
}
```

> **ملاحظة:** `$arguments` هي المعاملات اللي اتبعتت للقدرة — `[$announcement]` لو ناديت `can('publish', $announcement)`، أو `['Src\...\Announcement']` لو ناديت `can('create', Announcement::class)`. الشكل ده متحقّق منه من مصدر `Illuminate\Auth\Access\Gate`.

### المدير العام وحدود المستأجر (ADR-005 — مقبول)

**المدير العام محبوس في سياق المستأجر الحالي زيّه زيّ أي حد تاني.**

هو بيتجاوز **الصلاحيات** بس. تلات حاجات مابيتجاوزهاش:

| النوع | المثال | الآلية |
|---|---|---|
| قواعد السلامة | «مينفعش تحذف نفسك» | `invariants()` |
| حدود المستأجر | «سجل مؤسسة تانية» | `TenantBoundary` |
| قواعد الأعمال جوه القدرات المحمية | «الإعلان منشور بالفعل» | `rule()` جوه الـ Policy |

**الوصول لبيانات مستأجر تاني بيحصل بس عن طريق انتحال الشخصية** (`docs/12` بند ٣) — بصلاحيته
المنفصلة، وشريط التحذير، والتسجيل في `activity('security')`، والمدة القصوى ٣٠ دقيقة.
مفيش مسار تاني، ومفيش تجاوز شامل.

> ⚠️ **المستويين مكمّلين لبعض مش بديلين.** `Gate::before` بيرجّع `null` عشان الـ Policy تشتغل —
> فلو الـ Policy **مفيهاش** حارس المستأجر، المدير العام هيعدّي عادي (معاه كل الصلاحيات ومفيش
> قاعدة بتمنعه). عشان كده كل دالة في Policy بتاخد موديل تابع لمستأجر **لازم** تبدأ بـ:
>
> ```php
> ->ruleOrNotFound($record->tenant_id === app(TenantContext::class)->id(), 'record_not_found')
> ```
>
> الاختبار المعماري في بند ٩ بيرفض أي Policy ناسية الحارس ده.

**الاستثناء الوحيد** للقراءة عبر المستأجرين هو `TenantContext::withoutScope()`، وبشروطه:
أوامر console أو jobs مجدولة بس (مفيش HTTP)، بتعليق بيشرح ليه، قراءة بس، ونتيجة مجمّعة
مش صفوف خام. (`docs/20` بند ٣)

---

## ٦. اكتشاف السياسات في بنية DDD

الموديلات بتاعتنا مش في `App\Models`، فالاكتشاف التلقائي بتاع Laravel مش هيلاقيها. الحل مرة واحدة في `AuthorizationServiceProvider::boot()`:

```php
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

Gate::guessPolicyNamesUsing(function (string $model): ?string {
    // موديلات بره بنيتنا (Spatie\Permission\Models\Role،
    // Spatie\MediaLibrary\...\Media) مالهاش Policy عندنا.
    // null معناها «مفيش Policy» — من غيرها هنرجّع اسم كلاس مش موجود
    // ونخلّي أي تشخيص للتفويض مربك.
    if (! str_contains($model, '\\Domain\\Models\\')) {
        return null;
    }

    // Src\Contexts\Content\Domain\Models\Announcement
    //   → Src\Contexts\Content\Infrastructure\Policies\AnnouncementPolicy
    return Str::of($model)
        ->replace('\\Domain\\Models\\', '\\Infrastructure\\Policies\\')
        ->append('Policy')
        ->toString();
});
```

> **ليه `?string` مش `string`؟** الشكل القديم كان بيرجّع `Spatie\Permission\Models\RolePolicy`
> لأي موديل بره بنيتنا. Laravel بيتحقق بـ `class_exists` فمابيقعش، بس الاختبار
> `Gate::getPolicyFor($model) === null` بيبقى نتيجته صح بالصدفة مش بالتصميم. الرجوع بـ `null`
> صريح بيخلّي «مفيش Policy» حالة مقصودة.

كده أي Policy بتتبع الاصطلاح بتشتغل تلقائياً — من غير تسجيل يدوي لكل واحدة.

**للاستثناءات** (Policy مشتركة بين موديلين مثلاً)، استخدم الـ attribute على الموديل:

```php
use Illuminate\Database\Eloquent\Attributes\UsePolicy;

#[UsePolicy(SharedContentPolicy::class)]
final class Announcement extends Model { }
```

> `Gate::policy(Announcement::class, AnnouncementPolicy::class)` لسه شغّال، بس **متستخدموش** — لأنه بيتحط في مزوّد خدمة بعيد عن الموديل، فمحدش بيلاحظه لما يقرا الموديل.

---

## ٧. القدرات اللي مالهاش موديل (الصفحات والودجتس)

صفحة «صحة النظام» مالهاش موديل. برضه **مش** هنسأل عن الصلاحية مباشرة — هنعرّف Gate:

```php
// AuthorizationServiceProvider::boot()
//
// كل اسم صلاحية بيولّده الكتالوج بياخد Gate — مش الصفحات والودجتس بس.
// السبب: صفحات الإعدادات بتستخدم صلاحيات موارد (manage_appearance.settings)
// ومالهاش موديل، فمفيش Policy تستقبل السؤال. من غير الحلقة دي الصفحات دي
// بتبقى مقفولة على الكل. (docs/21-decisions.md → ADR-003)
foreach (app(PermissionBuilder::class)->allPermissionNames() as $ability) {
    Gate::define($ability, function (Authenticatable $user) use ($ability): Response {
        return $user->hasPermissionTo($ability, config('authorization.guard'))
            ? Response::allow()
            : Response::deny(__('authorization.denied.missing_permission', [
                'permission' => permission_label($ability),
            ]));
    });
}
```

> ⚠️ **الـ Gates دي مش باب خلفي للتفويض.** Laravel بيدّي الأولوية للـ Policy لما يكون فيه
> موديل في الاستدعاء — يعني `can('update', $announcement)` بيروح لـ `AnnouncementPolicy::update()`
> زي ما هو، والـ Gate اللي اسمه `update.announcements` مابيتنادىش. الـ Gates دي بتشتغل بس
> في الاستدعاءات اللي **مالهاش موديل**: `Gate::allows('access.health')`،
> `Gate::allows('manage_appearance.settings')`.
>
> **القاعدة الحديدية زي ما هي:** لو فيه موديل، السؤال يروح للـ Policy. لو استخدمت
> `Gate::allows('update.announcements')` عشان تتجنّب قاعدة أعمال في الـ Policy — ده كسر للقاعدة
> والاختبار المعماري في بند ٩ بيمسكه.

الاستخدام:

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

> `Gate::allows('access.health')` شكله شبه `can('access.health')` بس الفرق جوهري: فيه **Gate معرّف** بيتحكم في المنطق، فلو بكرة عايزين نضيف قاعدة («صفحة الصحة تتقفل أثناء الصيانة») بنغيّرها في مكان واحد. وممكن نختبرها لوحدها.

---

## ٨. الاستخدام في Filament

### المورد (Resource)

Filament بينادي الـ Policy تلقائياً — **مفيش داعي تكتب `canViewAny()` ولا `canCreate()` ولا `canEdit()`**. سيبهم واتأكد إن الـ Policy موجودة وبس.

الاستثناء الوحيد: التنقّل.

```php
public static function shouldRegisterNavigation(): bool
{
    return static::canViewAny();     // ← بتنادي الـ Policy، مش نص صلاحية
}
```

### الإجراءات

```php
// إجراء قياسي — بياخد الـ Policy لوحده
DeleteAction::make();

// إجراء مخصص — اسم القدرة، والسجل بيتبعت للـ Policy تلقائياً
Action::make('publish')
    ->authorize('publish')
    ->authorizationTooltip()          // ← بيعرض رسالة Response::deny() للمستخدم
    ->requiresConfirmation()
    ->action(fn (Announcement $record, array $data) => /* ... */);
```

> ⚠️ Filament بيضيف السجل تلقائياً كأول معامل للـ Policy. يعني `->authorize('publish')` على إجراء صف = `Gate::inspect('publish', $record)`. متمرّرش السجل بإيدك.

**متى `authorizationTooltip()` ومتى الإخفاء؟**

| الحالة | السلوك |
|---|---|
| مالوش الصلاحية أصلاً | **يختفي** — مالوش دعوة يعرف إن الميزة موجودة |
| معاه الصلاحية لكن قاعدة أعمال منعته | **يظهر معطّل + tooltip بالسبب** |

الفرق ده بيتحقق تلقائياً لو رسائل `missing_permission` كانت `null`:

```php
// في Decision::permission()
$this->denial = Response::deny();   // بدون رسالة → Filament بيخفي الإجراء
```

بينما `rule()` بترجّع رسالة دايماً → الإجراء بيظهر معطّل بالسبب. **ده بالظبط السلوك اللي عايزينه.**

### الإجراءات الجماعية — فحص كل سجل على حدة

```php
// ❌ فحص واحد شامل — بيتخطى قواعد الأعمال لكل سجل
DeleteBulkAction::make()->authorize('deleteAny');

// ✅ بيفحص كل سجل مختار بالـ Policy، وبيستبعد اللي رسب
DeleteBulkAction::make()
    ->authorizeIndividualRecords('delete');
```

`authorizeIndividualRecords()` بيمرّر كل سجل مختار على `AnnouncementPolicy::delete()`، وبيشيل اللي رسبوا من المجموعة قبل ما الإجراء يتنفّذ، وبيعرض للمستخدم كام سجل اتستبعد وليه.

> **قاعدة:** أي إجراء جماعي على سجلات ليها قواعد أعمال لازم `authorizeIndividualRecords()`. `authorize()` الشامل للحالات اللي القاعدة فيها على المورد كله بس.

### الحقول الحساسة

```php
// الشكل المعياري (ADR-009): نفس القدرة في الاتنين، والـ fallback لاسم الكلاس.
// ❌ متكتبش `$record !== null &&` — ده بيمنع الحفظ وقت الإنشاء أصلاً.
Toggle::make('is_pinned')
    ->visible(fn (?Announcement $record) => auth()->user()->can('pin', $record ?? Announcement::class))
    ->saved(fn (?Announcement $record) => auth()->user()->can('pin', $record ?? Announcement::class));
```

> ⚠️ `->visible(false)` **مش أمان** — القيمة ممكن توصل في الـ request. لازم تمنع الحفظ كمان.
>
> في Filament v4/v5 الطريقة الموثّقة هي **`->saved(false)`**. `->dehydrated(false)` لسه شغّال (و`isDehydrated()` بترجع لـ `isSaved()` لو مش متحددة) بس `saved()` هو الاسم الحالي في التوثيق. **تأكد من النسخة المثبّتة عندك** قبل ما تعتمد على واحد منهم، واكتب اختبار بيثبت إن القيمة مش بتتحفظ.

### ممنوع منعاً باتاً

```php
->skipAuthorization()      // ❌ بيعطّل كل فحوصات الـ Policy
```

الدالة دي موجودة فعلاً في Filament. لو لقيتها في PR — رفض فوري. لو فيه حالة محتاجاها، الحل هو Policy بتسمح، مش تعطيل الفحص.

---

## ٩. الاختبار المعماري (اللي بيفرض القاعدة)

```php
// tests/Architecture/AuthorizationTest.php

// ADR-010: النطاق بقى src + app + resources/views + tests.
// الاستثناء بتاع الـ Policies ضاق: hasRole على الهدف بس،
// و hasPermissionTo ممنوع فيها نهائياً.
const AUTHORIZATION_LAYER = [
    'Src\Support\Infrastructure\Authorization\Decision',
    'Src\Support\Infrastructure\Authorization\InvariantRegistry',
    'App\Providers\AuthorizationServiceProvider',
];

it('لا يُستخدم hasPermissionTo إلا في طبقة التفويض', function () {
    $violations = [];

    foreach (allPhpFiles([src_path(), app_path(), base_path('tests')]) as $file) {
        if (in_array(classFromPath($file), AUTHORIZATION_LAYER, true)) {
            continue;
        }

        // hasPermissionTo ممنوع في كل حتة تانية — بما فيها الـ Policies.
        // Decision::permission() هي اللي بتعمله، ومفيش سبب تكرره.
        if (preg_match('/->hasPermissionTo\(/', $file->getContents(), $m)) {
            $violations[] = "{$file->getRelativePathname()}: {$m[0]}";
        }
    }

    expect($violations)->toBeEmpty(
        "فحص صلاحية مباشر خارج طبقة التفويض:\n" . implode("\n", $violations)
    );
});

it('لا يُستخدم hasRole إلا في طبقة التفويض أو على هدف داخل Policy', function () {
    $violations = [];

    foreach (allPhpFiles([src_path(), app_path(), base_path('tests')]) as $file) {
        if (in_array(classFromPath($file), AUTHORIZATION_LAYER, true)) {
            continue;
        }

        $isPolicy = str_contains($file->getPathname(), '/Infrastructure/Policies/');

        foreach (matchAll('/(\$\w+)->(hasRole|hasAnyRole|hasAllRoles)\(/', $file->getContents()) as [$full, $var]) {
            // جوه Policy: مسموح على الهدف بس ($target)، ممنوع على الفاعل ($user).
            // مثال مشروع: ->rule(! $target->hasRole('super_admin'), 'cannot_impersonate_super_admin')
            if ($isPolicy && $var !== '$user') {
                continue;
            }

            $violations[] = "{$file->getRelativePathname()}: {$full}";
        }
    }

    expect($violations)->toBeEmpty(
        "فحص دور مباشر في غير محله:\n" . implode("\n", $violations)
    );
});

it('لا تُستخدم أسماء الصلاحيات كنصوص في Blade', function () {
    $separator = preg_quote(config('authorization.separator'), '/');
    $violations = [];

    // @can('update', $user)              ← اسم قدرة، مسموح
    // @can('publish.announcements')      ← نص صلاحية، ممنوع
    $pattern = "/@(can|cannot|canany)\(\s*'([a-z_]+{$separator}[a-z_.]+)'/";

    foreach (File::allFiles(resource_path('views')) as $file) {
        if (! preg_match_all($pattern, $file->getContents(), $m, PREG_SET_ORDER)) {
            continue;
        }

        foreach ($m as $match) {
            // الصفحات والودجتس ليها Gates معرّفة — استثناء مشروع
            if (str_starts_with($match[2], 'access.') || str_starts_with($match[2], 'widget.')) {
                continue;
            }

            $violations[] = "{$file->getRelativePathname()}: @{$match[1]}('{$match[2]}')";
        }
    }

    expect($violations)->toBeEmpty(
        "نص صلاحية في Blade — استخدم @can('ability', \$model):\n" . implode("\n", $violations)
    );
});

// ADR-007: الحارس مستحيل ينتسي، بس الاختبار ده بيمسك اللي استخدم
// decide() على دالة بتاخد سجل — يعني فات المدخل الصح.
it('كل دالة Policy تأخذ سجلاً تستخدم decideFor', function () {
    $violations = [];

    foreach (allPolicies() as $policy) {
        foreach ((new ReflectionClass($policy))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getNumberOfParameters() < 2) {
                continue;   // viewAny/create — decide() هو الصح
            }

            if (in_array($method->name, ['invariants', 'isInvariant', 'permissionFor'], true)) {
                continue;
            }

            if (! str_contains(methodSource($method), '$this->decideFor(')) {
                $violations[] = "{$policy}::{$method->name}()";
            }
        }
    }

    expect($violations)->toBeEmpty(
        "دوال Policy بتاخد سجل بس مستخدماش decideFor() — حارس المستأجر مش مطبّق (ADR-007):\n"
        . implode("\n", $violations)
    );
});

it('لا تُستخدم أسماء الصلاحيات كنصوص في can()', function () {
    $separator = preg_quote(config('authorization.separator'), '/');
    $violations = [];

    foreach (allPhpFiles([src_path(), app_path(), base_path('tests')]) as $file) {
        // can('publish.announcements') ← نص صلاحية، ممنوع
        // can('publish', $record)      ← اسم قدرة، مسموح
        if (preg_match_all("/->can\(\s*'([a-z_]+{$separator}[a-z_]+)'\s*\)/", $file->getContents(), $m)) {
            foreach ($m[1] as $permission) {
                // استثناء: قدرات الصفحات والودجتس لها Gates معرّفة
                if (str_starts_with($permission, 'access.') || str_starts_with($permission, 'widget.')) {
                    continue;
                }

                $violations[] = "{$file->getRelativePathname()}: can('{$permission}')";
            }
        }
    }

    expect($violations)->toBeEmpty(
        "استخدام اسم صلاحية بدل اسم قدرة — استخدم can('ability', \$model):\n" . implode("\n", $violations)
    );
});

it('لا يُستخدم skipAuthorization', function () {
    expect(grepAll('/->skipAuthorization\(/', [src_path(), app_path(), resource_path('views')]))->toBeEmpty();
});

it('كل موديل تابع لمستأجر له Policy', function () {
    $violations = [];

    foreach (allDomainModels() as $model) {
        if (Gate::getPolicyFor($model) === null) {
            $violations[] = $model;
        }
    }

    expect($violations)->toBeEmpty('موديلات بدون Policy: ' . implode(', ', $violations));
});

it('كل Policy ترث الكلاس الأساسي', function () {
    expect('Src\Contexts\*\Infrastructure\Policies')
        ->toExtend(Src\Support\Infrastructure\Authorization\Policy::class);
});

it('كل Policy ترجّع Response وليس bool', function () {
    foreach (allPolicies() as $policy) {
        foreach ((new ReflectionClass($policy))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (in_array($method->name, ['invariants', 'isInvariant', 'permissionFor'], true)) {
                continue;
            }

            expect((string) $method->getReturnType())
                ->toBe(Response::class, "{$policy}::{$method->name}() لازم ترجّع Response");
        }
    }
});
```

---

## ١٠. اختبار السياسات نفسها

الميزة الكبرى للنمط ده: تقدر تختبر التفويض **من غير ما تشغّل واجهة**.

```php
// مصفوفة الصلاحيات — دور × قدرة
dataset('announcement_matrix', [
    ['super_admin', 'viewAny', true],
    ['super_admin', 'create',  true],
    ['admin',       'viewAny', true],
    ['admin',       'create',  true],
    ['editor',      'create',  true],
    ['editor',      'publish', false],
    ['viewer',      'viewAny', true],
    ['viewer',      'create',  false],
]);

it('يطبّق مصفوفة الصلاحيات', function (string $role, string $ability, bool $allowed) {
    $user = userWithRole($role);

    expect($user->can($ability, Announcement::class))
        ->toBe($allowed, "الدور {$role} — القدرة {$ability}");
})->with('announcement_matrix');


// قواعد الأعمال — كل واحدة برسالتها
it('يمنع نشر إعلان منشور بالفعل ويوضّح السبب', function () {
    $user = userWithRole('admin');
    $announcement = Announcement::factory()->published()->create();

    $response = Gate::forUser($user)->inspect('publish', $announcement);

    expect($response->denied())->toBeTrue()
        ->and($response->message())->toBe(__('authorization.denied.announcement.not_draft'));
});

it('يمنع نشر إعلان ناقص العنوان الاحتياطي', function () {
    $user = userWithRole('admin');
    $announcement = Announcement::factory()->draft()->create([
        'title' => ['ar' => 'عنوان'],      // ناقص en
    ]);

    expect(Gate::forUser($user)->inspect('publish', $announcement)->denied())->toBeTrue();
});

it('يخفي وجود سجل من مستأجر آخر بـ 404 لا 403', function () {
    [$a, $b] = Tenant::factory()->count(2)->create();
    $user = userWithRole('admin', $a);
    $foreign = Announcement::factory()->create(['tenant_id' => $b->id]);

    $response = Gate::forUser($user)->inspect('view', $foreign);

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
});


// ══════ الاختبار الأهم: قواعد السلامة تسري على المدير العام ══════

it('لا يتجاوز المدير العام قواعد السلامة', function () {
    $super = userWithRole('super_admin');
    $published = Announcement::factory()->published()->create();

    // معاه كل الصلاحيات...
    expect($super->can('publish.announcements'))->toBeTrue();

    // ...ومع ذلك القاعدة بتمنعه
    expect($super->can('publish', $published))->toBeFalse();
    expect($super->can('delete', $published))->toBeFalse();
});

it('يتجاوز المدير العام الصلاحيات العادية', function () {
    $super = userWithRole('super_admin');
    $draft = Announcement::factory()->draft()->create();

    expect($super->can('viewAny', Announcement::class))->toBeTrue()
        ->and($super->can('publish', $draft))->toBeTrue();
});


// ══════ ADR-005: المدير العام مابيعديش حدود المستأجر ══════

it('لا يتجاوز المدير العام حدود المستأجر', function () {
    [$a, $b] = Tenant::factory()->count(2)->create();
    $super = userWithRole('super_admin', $a);

    app(TenantContext::class)->set($b->id);
    $foreign = Announcement::factory()->draft()->create(['tenant_id' => $b->id]);

    app(TenantContext::class)->set($a->id);

    // معاه كل الصلاحيات...
    expect($super->can('view.announcements'))->toBeTrue();

    // ...ومع ذلك سجل المستأجر التاني مش موجود بالنسبة له
    $response = Gate::forUser($super)->inspect('view', $foreign);

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);      // 404 مش 403 — مانأكدش إنه موجود
});

it('يمنع المدير العام من تعديل أو حذف سجل مستأجر آخر', function () {
    [$a, $b] = Tenant::factory()->count(2)->create();
    $super = userWithRole('super_admin', $a);

    app(TenantContext::class)->set($b->id);
    $foreign = Announcement::factory()->draft()->create(['tenant_id' => $b->id]);

    app(TenantContext::class)->set($a->id);

    expect($super->can('update', $foreign))->toBeFalse()
        ->and($super->can('delete', $foreign))->toBeFalse();
});

it('يظل المدير العام قادراً داخل مستأجره', function () {
    $tenant = Tenant::factory()->create();
    $super  = userWithRole('super_admin', $tenant);

    app(TenantContext::class)->set($tenant->id);
    $own = Announcement::factory()->draft()->create(['tenant_id' => $tenant->id]);

    expect($super->can('view', $own))->toBeTrue()
        ->and($super->can('update', $own))->toBeTrue();
});

it('لا يستطيع أي مستخدم حذف نفسه', function () {
    foreach (['super_admin', 'admin'] as $role) {
        $user = userWithRole($role);
        expect($user->can('delete', $user))->toBeFalse("الدور {$role} قدر يحذف نفسه");
    }
});
```

> **معيار قبول:** كل Policy لها ملف اختبار فيه (أ) مصفوفة الأدوار × القدرات، (ب) اختبار لكل قاعدة أعمال برسالتها، (ج) اختبار إن المدير العام مابيتخطاش قواعد السلامة.

---

## ١١. رسائل الرفض

`lang/ar/authorization.php` — قسم جديد:

```php
'denied' => [
    // عام
    'missing_permission' => 'مش معاك صلاحية «:permission».',
    'record_trashed'     => 'السجل ده محذوف. استرجعه الأول.',
    'record_not_found'   => 'السجل ده مش موجود.',
    'maintenance_mode'   => 'النظام في وضع الصيانة دلوقتي.',
    'self_target'        => 'مينفعش تعمل كده على حسابك.',
    'last_admin'         => 'ده آخر مدير في المؤسسة — مينفعش تشيله.',
    'while_impersonating'=> 'العملية دي مقفولة أثناء انتحال الشخصية.',

    // خاص بالإعلانات
    'announcement' => [
        'not_draft'              => 'الإعلان ده منشور بالفعل.',
        'published_must_archive' => 'الإعلان المنشور بيتأرشف مش بيتحذف.',
        'missing_fallback_title' => 'لازم تكتب العنوان بلغة :locale قبل النشر.',
        'already_expired'        => 'تاريخ انتهاء الإعلان عدّى — عدّله الأول.',
        'pin_requires_published' => 'مينفعش تثبّت إعلان مش منشور.',
        'pin_limit_reached'      => 'وصلت للحد الأقصى (:limit) من الإعلانات المثبّتة.',
        'published_needs_publish_permission' => 'الإعلان منشور — تعديله محتاج صلاحية النشر.',
    ],
],
```

### قواعد كتابة رسالة الرفض

الرسالة دي **بتظهر للمستخدم**، فهي جزء من المنتج مش من الكود:

- **قول السبب، مش «ممنوع».** ❌ «غير مصرّح» · ✅ «الإعلان ده منشور بالفعل»
- **قول الحل لو فيه.** «السجل ده محذوف. **استرجعه الأول**.»
- **متسربش معلومات.** رسالة رفض بسبب مستأجر تاني لازم تقول «مش موجود» مش «مش بتاعك».
- **مترجمة عربي وإنجليزي.** زي أي نص تاني.

---

## ١٢. المسار الكامل — من الزرار للقاعدة

```
المستخدم يشوف الزرار
        ↓
Filament: ->authorize('publish')
        ↓
Gate::inspect('publish', $announcement)
        ↓
Gate::before  →  super_admin؟ و'publish' مش invariant؟ → true (انتهى)
        ↓ null
Gate::guessPolicyNamesUsing → AnnouncementPolicy
        ↓
AnnouncementPolicy::publish($user, $announcement)
        ↓
Decision
  ├─ permission()  → معاه publish.announcements؟  → لأ: deny() بدون رسالة → الزرار يختفي
  ├─ rule()        → مش محذوف؟                    → لأ: deny('record_trashed')
  ├─ rule()        → لسه مسودة؟                   → لأ: deny('announcement.not_draft')
  ├─ rule()        → العنوان الاحتياطي موجود؟      → لأ: deny('missing_fallback_title')
  └─ rule()        → لسه مانتهاش؟                  → لأ: deny('already_expired')
        ↓ كلها عدّت
Response::allow()
        ↓
الزرار ظاهر ومفعّل — والـ Action ينفّذ
```

**نفس المسار بالظبط** بيتنفّذ لو الطلب جه من API أو Job أو Command — لأن الفحص في الـ Policy مش في الواجهة.

---

## ١٣. الطبقة التانية: الـ Action برضه بيتحقق

الواجهة بتخفي، الـ Policy بتمنع، والـ **Action بيتأكد**. تلات طبقات:

```php
final readonly class PublishAnnouncementAction
{
    public function handle(PublishAnnouncementData $data, ?User $actor = null): Announcement
    {
        $announcement = Announcement::findOrFail($data->announcementId);

        // الفحص هنا مش تكرار — ده الحاجز الأخير.
        // الـ Action ممكن يتنادى من Command أو Job أو Test أو API.
        if ($actor !== null) {
            Gate::forUser($actor)->authorize('publish', $announcement);
        }

        return DB::transaction(fn () => /* ... */);
    }
}
```

> `Gate::authorize()` (مش `allows()`) بترمي `AuthorizationException` بالرسالة بتاعة الـ Policy — فالمستخدم بياخد نفس السبب المفهوم.
>
> `$actor` اختياري بـ `null` عشان الأوامر المجدولة والـ Jobs النظامية تعدّي — بس لازم تكون **صريحة** إنها بتعدّي، مش بالسهو.

---

## ١٤. الترحيل من الكود القديم

لو لقيت الشكل ده في كود موجود:

```php
->visible(fn () => auth()->user()->can('publish.announcements'))
```

الخطوات:

1. دوّر على **كل** الأماكن اللي فيها نفس الفحص (`grep -rn "publish.announcements" src/`)
2. اجمع **كل** شروط الأعمال اللي حواليها (`if ($record->status === ...)` جوه الـ `->action()`)
3. انقلهم كلهم لـ `AnnouncementPolicy::publish()` كـ `->rule()` لكل شرط برسالته
4. بدّل كل الأماكن بـ `->authorize('publish')->authorizationTooltip()`
5. اكتب اختبار لكل قاعدة نقلتها
6. شغّل الاختبار المعماري — لازم يعدّي

---

## ١٥. معايير القبول

- [ ] `Policy` و`Decision` و`InvariantRegistry` و`PermissionBuilder` و`TenantBoundary`
      كلهم في `src/Support/Infrastructure/Authorization` — مفيش حاجة منهم في `Domain` (ADR-006)
- [ ] `Decision::permission()` بيستخدم `config('authorization.guard')` مش `filament()` (ADR-006)
- [ ] كل دالة Policy بتاخد سجل بتستخدم `decideFor()` — الاختبار المعماري بيثبت (ADR-007)
- [ ] كل Policy بترث `Policy` وكل دوالها بترجّع `Response` مش `bool`
- [ ] `Gate::guessPolicyNamesUsing()` مضبوط ومفيش `Gate::policy()` يدوي
- [ ] `Gate::before` بيحترم قواعد السلامة — اختبار بيثبت إن المدير العام مابيتخطاهاش
- [ ] صفر `hasPermissionTo()` / `hasRole()` بره طبقة التفويض — الاختبار المعماري بيثبت
- [ ] صفر `can('action.resource')` بنص صلاحية — الاختبار المعماري بيثبت
- [ ] صفر `skipAuthorization()` في الكود كله
- [ ] كل موديل له Policy — الاختبار بيثبت
- [ ] كل إجراء جماعي على سجلات ذات قواعد يستخدم `authorizeIndividualRecords()`
- [ ] كل الحقول الحساسة عليها منع حفظ فعلي (`saved(false)`) مش إخفاء بس — واختبار بيثبت
- [ ] كل قاعدة أعمال ليها رسالة رفض مترجمة عربي وإنجليزي
- [ ] كل Action حساس بيعمل `Gate::authorize()` كطبقة تالتة
- [ ] كل Policy ليها ملف اختبار: مصفوفة أدوار + قاعدة أعمال لكل rule + اختبار المدير العام
