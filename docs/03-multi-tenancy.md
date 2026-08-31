# ٠٣ — تعدد المستأجرين (Multi-Tenancy)

## القرار المعماري

**قاعدة بيانات واحدة + عمود `tenant_id`** (single database, row-level isolation).

**ليه مش قاعدة لكل مستأجر؟**
- الميجريشنز بتتنفّذ مرة واحدة مش ٥٠ مرة
- التقارير عبر المستأجرين ممكنة
- تكلفة أقل بكتير على Postgres واحد
- Filament بيدعم النمط ده جاهز

**التكلفة:** أي استعلام ناسي الـ scope = تسريب بيانات. عشان كده الحماية على **٤ طبقات**.

---

## ١. جدول المستأجرين

> 📍 **مكان الموديل: `Src\Support\Domain\Models\Tenant`** — نواة مشتركة، مش سياق. (ADR-011)
>
> `BelongsToTenant` في `Support` بيستورده، و`User` في `Identity` بيستورده. لو كان في
> `Contexts\Tenancy` كان الأساس المشترك بيعتمد على سياق — اتجاه اعتماد مقلوب.
>
> `Tenant` **مابيستخدمش** `BelongsToTenant` — هو اللي بيعرّف الحد.

```php
Schema::create('tenants', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();          // acme
    $table->string('domain')->nullable()->unique(); // acme.fc-admin.test
    $table->json('name');                      // {"ar": "...", "en": "..."} — spatie/translatable
    $table->string('logo_path')->nullable();
    $table->string('primary_color', 9)->default('#12454F');
    $table->boolean('is_active')->default(true);
    $table->timestamp('trial_ends_at')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['is_active', 'deleted_at']);
});
```

الربط بالمستخدمين (many-to-many — مستخدم ممكن يكون في أكتر من مؤسسة):

```php
Schema::create('tenant_user', function (Blueprint $table) {
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->timestamp('joined_at')->useCurrent();
    $table->primary(['tenant_id', 'user_id']);

    // للاستعلام العكسي: «كل مستخدمي المؤسسة دي»
    $table->index(['user_id', 'tenant_id']);
});
```

### ⚠️ جدول `users` **مفيهوش** عمود `tenant_id`

القرار ده صريح ومقصود (`docs/21-decisions.md` → ADR-002). العضوية بتتحدد بجدول `tenant_user` بس.

**تلات أسباب:**

١. **`teams => true` بيتطلب كده.** بند ١ في `docs/02` بيقول «نفس المستخدم ممكن يكون `admin` في
   مستأجر و`viewer` في مستأجر تاني». ده مستحيل مع عمود `tenant_id` واحد على المستخدم.

٢. **`BelongsToTenant` على `User` بيكسر تسجيل الدخول.** الـ `TenantScope` بيرمي
   `MissingTenantContextException` لو مفيش سياق. لكن الـ login و`getTenants()` ومبدّل المستأجر
   كلهم بيستعلموا على `users` **قبل** ما يبقى فيه سياق أصلاً. يعني محدش هيقدر يدخل.

٣. الوثائق كلها أصلاً بتستخدم `User::factory()->hasAttached($tenant)` — ده النموذج الفعلي.

### القاعدة العامة

> الموديلات اللي **بتعرّف** حدود المستأجر (`Tenant`) أو **بتعبر** الحدود دي (`User`) مابتستخدمش
> `BelongsToTenant`. الموديلات اللي **جوه** الحدود بتستخدمه إلزامياً.

عزل المستخدمين بيتحقق بفلترة على العلاقة، مش بـ global scope:

```php
// ✅ الشكل الصحيح لجلب مستخدمي مستأجر
User::query()->whereHas('tenants', fn ($q) => $q->whereKey($tenantId))->get();

// ❌ مش هيشتغل — مفيش عمود tenant_id على users
User::query()->where('tenant_id', $tenantId)->get();
```

---

## ٢. الطبقة ١ — Filament Tenancy

في `AdminPanelProvider`:

```php
use Src\Support\Domain\Models\Tenant;      // نواة مشتركة — ADR-011

return $panel
    ->tenant(Tenant::class, slugAttribute: 'slug')
    ->tenantRoutePrefix('t')                     // /admin/t/acme/users
    ->searchableTenantMenu()
    ->tenantMenuItems([
        'profile' => Action::make('profile')
            ->label(__('tenancy.menu.settings'))
            ->url(fn () => TenantSettingsPage::getUrl())
            ->visible(fn () => auth()->user()->can('update', Filament::getTenant())),
    ])
    ->tenantRegistration(RegisterTenant::class)   // اختياري
    ->tenantProfile(EditTenantProfile::class);
```

على موديل المستخدم:

```php
use Filament\Models\Contracts\HasTenants;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)->withTimestamps();
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->tenants()->where('is_active', true)->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->tenants()->whereKey($tenant)->exists();
    }
}
```

Filament v5 بيعمل الـ scoping والربط التلقائي للسجلات الجديدة **لو** المورد معرّف علاقة المستأجر:

```php
class UserResource extends Resource
{
    protected static ?string $tenantOwnershipRelationshipName = 'tenant';
    // أو للعلاقات المتعددة:
    protected static ?string $tenantRelationshipName = 'users';
}
```

> ⚠️ Filament بيغطّي موارده هو بس. أي استعلام إنت كاتبه بإيدك (في Action، Job، Command، API) **مش محمي** — عشان كده الطبقة ٢.

---

## ٣. الطبقة ٢ — Global Scope على مستوى Eloquent

الحماية الحقيقية. `src/Support/Infrastructure/Persistence/Concerns/BelongsToTenant.php`:

```php
namespace Src\Support\Infrastructure\Persistence\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Scope;
use Src\Support\Domain\Models\Tenant;              // نواة مشتركة — ADR-011
use Src\Support\Application\Contracts\TenantContext;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (Model $model): void {
            if ($model->getAttribute('tenant_id') !== null) {
                return;
            }

            $tenantId = app(TenantContext::class)->id();

            if ($tenantId === null) {
                throw new MissingTenantContextException(static::class);
            }

            $model->setAttribute('tenant_id', $tenantId);
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId === null) {
            // في CLI/Job من غير سياق: نرمي استثناء بدل ما نسرّب كل الصفوف
            if (! app(TenantContext::class)->isBypassed()) {
                throw new MissingTenantContextException($model::class);
            }

            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
```

`TenantContext` — خدمة واحدة تعرف المستأجر الحالي من أي مكان:

> ⚠️ **لازم يتسجّل كـ singleton.** الكلاس فيه حالة قابلة للتغيير — لو اتسجّل `bind` عادي،
> كل `app(TenantContext::class)` هترجّع نسخة جديدة سياقها فاضي، والعزل بيقع بصمت.
>
> ```php
> // AppServiceProvider::register()
> $this->app->singleton(TenantContext::class);
> $this->app->alias(TenantContext::class, TenantContextContract::class);
> ```
>
> ⚠️ **ترتيب معاملات `alias()` مش تفصيلة.** الشكل المقلوب:
>
> ```php
> $this->app->singleton(TenantContextContract::class, TenantContext::class);
> $this->app->alias(TenantContextContract::class, TenantContext::class);   // ❌
> ```
>
> بيعمل **تكرار لا نهائي** في الـ container: `alias($abstract, $alias)` بيسجّل
> `aliases[$alias] = $abstract`، يعني الاسم المحسوس بقى alias للعقد — والعقد
> تنفيذه هو نفس الاسم المحسوس. حلّ العقد → يبني المحسوس → يرجع للعقد → دورة.
> النتيجة استنفاد الذاكرة، مش رسالة خطأ مفهومة. (اتكشف في Phase 4 — [ADR-015](21-decisions.md#adr-015))

```php
namespace Src\Support\Infrastructure\Tenancy;

final class TenantContext implements TenantContextContract
{
    private ?int $tenantId = null;

    /** الفرق بين «مامتضبطش» و«اتضبط بـ null» — ADR-008 */
    private bool $isSet = false;

    private bool $bypassed = false;

    public function id(): ?int
    {
        // اتضبط صراحةً؟ احترم القيمة حتى لو null.
        // من غير الفلاج ده، set(null) مابيمسحش السياق جوه طلب لوحة —
        // الـ ?? بترجع لمستأجر Filament، و«امسح السياق» بتبقى مستحيلة.
        if ($this->isSet) {
            return $this->tenantId;
        }

        return Filament::getTenant()?->getKey();
    }

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->isSet    = true;
        $this->syncDependents($tenantId);
    }

    /** ارجع لسلوك «خد المستأجر من Filament» */
    public function forget(): void
    {
        $this->tenantId = null;
        $this->isSet    = false;
        $this->syncDependents(null);
    }

    public function isBypassed(): bool
    {
        return $this->bypassed;
    }

    /**
     * قراءة عبر كل المستأجرين — بشروط `docs/20` بند ٣-٧:
     * أوامر console أو jobs بس، قراءة بس، نتيجة مجمّعة، وبتعليق يشرح ليه.
     */
    public function withoutScope(callable $callback): mixed
    {
        $previous = $this->bypassed;
        $this->bypassed = true;

        try {
            return $callback();
        } finally {
            $this->bypassed = $previous;
        }
    }

    public function forEachTenant(callable $callback): void
    {
        // ->get()->all() مش ->cursor(): الـ cursor بيقرا كسول، والـ bypass
        // بيتقفل في الـ finally قبل ما يتجاب صف واحد. لازم الصفوف تتقري
        // والـ bypass لسه مفتوح.
        $tenants = $this->withoutScope(
            fn () => Tenant::query()->where('is_active', true)->get()->all(),
        );

        $previous = $this->isSet ? $this->tenantId : null;
        $hadContext = $this->isSet;

        try {
            foreach ($tenants as $tenant) {
                $this->set($tenant->id);
                $callback($tenant);
            }
        } finally {
            // رجّع السياق الأصلي — مش set(null) اللي بتسيب isSet = true
            $hadContext ? $this->set($previous) : $this->forget();
        }
    }

    private function syncDependents(?int $tenantId): void
    {
        // ١. spatie/permission teams — ده اللي بيعزل كاش الصلاحيات فعلياً
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ٢. سياق اللوج
        Log::shareContext(['tenant_id' => $tenantId]);

        // ملاحظة: مفيش config(['cache.prefix' => ...]) هنا.
        // الـ store بيتبني مرة واحدة والبادئة بتتحط جواه وقت الإنشاء، فتغيير
        // الكونفيج بعد كده مالوش أي أثر. عزل كاش التطبيق بيتعمل بالـ tags
        // في مكان الاستدعاء. (ADR-008)
    }
}
```

> **لو عدد المستأجرين كبر:** `->get()->all()` بتحمّلهم كلهم في الذاكرة. بالمئات ده مقبول.
> لو وصلنا لعشرات الآلاف، الحل `chunkById` **جوه** الـ `withoutScope` — مش `cursor` بره منه.

### عزل الكاش — آليتين، مش تلاتة

| النوع | الآلية | فين |
|---|---|---|
| كاش الصلاحيات | `setPermissionsTeamId()` + `forgetCachedPermissions()` | `syncDependents()` فوق |
| كاش التطبيق | `Cache::tags([..., "tenant:{$id}"])` | مكان الاستدعاء — `02`, `05`, `07`, `16` |

```php
// الشكل المعياري لأي كاش تابع لمستأجر
Cache::tags(['nav-badges', "tenant:" . app(TenantContext::class)->id()])
    ->remember($key, $ttl, $callback);
```

> ⚠️ الـ tags بتتطلب store بيدعمها — **Redis** و`array` أيوه، `file` و`database` لأ.
> ده متسق مع الستاك (`CACHE_STORE=redis`، والاختبارات على `array`).

---

## ٤. الطبقة ٣ — عزل كاش الصلاحيات (مصيدة حقيقية)

**المشكلة:** `PermissionServiceProvider` بيقرأ `permission.cache.key` أثناء `boot()` — قبل ما ميدلوير المستأجر يشتغل. يعني لو غيّرت البادئة بعد كده، الباكدج مش هيشوفها، وصلاحيات مستأجر ممكن تتسرّب لمستأجر تاني في نفس العملية.

**الحل:** بعد أي تغيير للمستأجر في نفس الطلب، أعد تهيئة الكاش صراحةً:

```php
app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
app(PermissionRegistrar::class)->forgetCachedPermissions();
```

ده اللي `TenantContext::syncDependents()` فوق بيعمله.

**ميدلوير الحماية:**

```php
namespace Src\Support\Presentation\Http\Middleware;

final class InitializeTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        app(TenantContext::class)->set($tenant?->getKey());

        return $next($request);
    }
}
```

سجّله في اللوحة:

```php
->tenantMiddleware([
    InitializeTenantContext::class,
], isPersistent: true)
```

**في الطوابير:** الـ Job مش شايف الـ context. لازم تمرّر `tenant_id` صراحةً:

```php
final class SendMonthlyReportJob implements ShouldQueue
{
    public function __construct(public int $tenantId) {}

    public function handle(TenantContext $context): void
    {
        $context->set($this->tenantId);
        // ... الشغل
    }
}
```

اعمل `TenantAwareJob` كـ base class فيه ده جاهز — عشان محدش ينسى.

---

## ٥. الطبقة ٤ — الاختبارات

الطبقة الوحيدة اللي بتثبت إن التلاتة اللي فوق شغّالين.

```php
it('يمنع مستأجر من رؤية بيانات مستأجر آخر', function () {
    [$acme, $beta] = Tenant::factory()->count(2)->create();

    $acmeUser = User::factory()->hasAttached($acme)->create();
    Post::factory()->count(3)->create(['tenant_id' => $acme->id]);
    Post::factory()->count(5)->create(['tenant_id' => $beta->id]);

    app(TenantContext::class)->set($acme->id);

    expect(Post::count())->toBe(3);
});

it('يرفض إنشاء سجل بدون سياق مستأجر', function () {
    app(TenantContext::class)->set(null);

    expect(fn () => Post::factory()->create())
        ->toThrow(MissingTenantContextException::class);
});

it('لا يسرّب صلاحيات بين المستأجرين في نفس الطلب', function () {
    [$acme, $beta] = Tenant::factory()->count(2)->create();
    $user = User::factory()->hasAttached([$acme, $beta])->create();

    app(TenantContext::class)->set($acme->id);
    $user->assignRole('admin');

    app(TenantContext::class)->set($beta->id);

    expect($user->fresh()->hasRole('admin'))->toBeFalse();
});
```

### اختبار شامل تلقائي (الأهم)

```php
it('كل موديل تابع لمستأجر عليه الـ trait', function () {
    $models = collect(File::allFiles(base_path('src/Contexts')))
        ->filter(fn ($f) => str_contains($f->getPathname(), '/Domain/Models/'))
        ->map(fn ($f) => classFromPath($f))
        ->filter(fn ($c) => Schema::hasColumn((new $c)->getTable(), 'tenant_id'));

    foreach ($models as $model) {
        expect(class_uses_recursive($model))
            ->toContain(BelongsToTenant::class, "الموديل {$model} فيه tenant_id بدون الـ trait");
    }
});
```

الاختبار ده بيمسك أي موديل جديد نسي الـ trait — وده أخطر باگ ممكن يحصل في التطبيق.

---

## ٦. الوسائط والإعدادات لكل مستأجر

- **Media:** المسار بيتولّد بـ `PathGenerator` مخصص يبدأ بـ `tenants/{tenant_id}/` — تفاصيل في `docs/04-media-filesystem.md`
- **Settings:** الإعدادات على مستويين — عامة (global) وخاصة بالمستأجر. تفاصيل في `docs/05-settings.md`
- **الكاش:** بادئة لكل مستأجر (`fc_t{id}`) — اتظبطت فوق
- **اللوج:** `tenant_id` في كل سطر — تفاصيل في `docs/11-logging-monitoring.md`

---

## ٧. معايير القبول

- [ ] Filament بيعرض قائمة تبديل المستأجر ومحدش يقدر يدخل مستأجر مش عضو فيه
- [ ] كل موديل فيه `tenant_id` عليه `BelongsToTenant` — الاختبار التلقائي بيثبت ده
- [ ] استعلام من غير سياق مستأجر بيرمي استثناء مش بيرجّع كل الصفوف
- [ ] `spatie/permission` بـ `teams => true` والأدوار معزولة — الاختبار بيثبت
- [ ] كل Job فيه `tenantId` صريح
- [ ] بادئة الكاش مختلفة لكل مستأجر — مؤكد بـ `redis-cli KEYS 'fc_t*'`
- [ ] `TenantContext::forEachTenant()` شغّال للأوامر المجدولة
- [ ] عمود `tenant_id` عليه index مركّب مع أكتر عمود بيتفلتر عليه في كل جدول
- [ ] **العزل بيسري على `super_admin` كمان** — اختبار بيثبت إنه بياخد 404 على سجل مستأجر تاني
      (`docs/19` بند ١٠ · ADR-005)
- [ ] `TenantBoundary` موجود و`Gate::before` بيستدعيه — مفيش تجاوز شامل عابر للمستأجرين

> **الطبقات الأربع بتسري على كل الأدوار بلا استثناء.** المدير العام بيتجاوز الصلاحيات بس —
> مش حدود المستأجر ولا قواعد السلامة. الوصول لبيانات مستأجر تاني بيحصل عن طريق انتحال
> الشخصية بس (`docs/12` بند ٣). القرار في `docs/21-decisions.md` → ADR-005.
