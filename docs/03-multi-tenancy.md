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
});
```

---

## ٢. الطبقة ١ — Filament Tenancy

في `AdminPanelProvider`:

```php
use Src\Contexts\Tenancy\Domain\Models\Tenant;

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
use Src\Contexts\Tenancy\Domain\Models\Tenant;
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

```php
namespace Src\Support\Infrastructure\Tenancy;

final class TenantContext implements TenantContextContract
{
    private ?int $tenantId = null;
    private bool $bypassed = false;

    public function id(): ?int
    {
        return $this->tenantId ?? Filament::getTenant()?->getKey();
    }

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->syncDependents($tenantId);
    }

    /** للأوامر والتقارير عبر كل المستأجرين — استخدمه بحذر شديد */
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
        $this->withoutScope(fn () => Tenant::query()->where('is_active', true)->cursor())
            ->each(function (Tenant $tenant) use ($callback): void {
                $this->set($tenant->id);
                $callback($tenant);
            });

        $this->set(null);
    }

    private function syncDependents(?int $tenantId): void
    {
        // ١. spatie/permission teams
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ٢. بادئة الكاش
        config(['cache.prefix' => 'fc_t' . ($tenantId ?? 'global')]);

        // ٣. سياق اللوج
        Log::shareContext(['tenant_id' => $tenantId]);
    }
}
```

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
