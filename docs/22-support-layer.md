# ٢٢ — عقد بناء طبقة `Support`

> **الملف ده مخطّط تنفيذ، مش شرح.** كل كلاس هنا له: مكانه، مسؤوليته، اللي مسموح له يستورده،
> والاختبار اللي بيثبت إنه خلص. لو محتاج تخمّن حاجة وإنت بتنفّذ — الوثيقة ناقصة، بلّغ.
>
> **اقرا الأول:** `docs/01` (الطبقات) · `docs/19` (التفويض) · `docs/21` (القرارات — الحكم عند أي تعارض)

---

## ليه الملف ده موجود

`Src\Support` هو الأساس اللي كل سياق بيقف عليه. كان موصوف متفرّق على `docs/01` و`03` و`19`،
ومحدش كان يقدر يجاوب على سؤال بسيط: **«أبدأ منين، وأعرف إمتى خلصت؟»**

الترتيب هنا **مش اقتراح** — هو ترتيب الاعتماديات. كل خطوة بتشتغل بس لو اللي قبلها خلصت.

---

## ١. الشكل النهائي

```
src/Support/
├── Domain/                              ← صفر استيراد من أي إطار
│   ├── Models/
│   │   └── Tenant.php                   ← نواة مشتركة (ADR-011)
│   ├── ValueObjects/
│   │   ├── TenantId.php
│   │   ├── Email.php
│   │   └── PhoneNumber.php
│   ├── Events/
│   │   └── DomainEvent.php              (abstract)
│   └── Exceptions/
│       ├── DomainException.php          (abstract)
│       └── MissingTenantContextException.php
│
├── Application/
│   ├── Contracts/
│   │   ├── TenantContext.php            (interface)
│   │   ├── DiskResolver.php             (interface)
│   │   └── Clock.php                    (interface)
│   └── Concerns/
│
├── Infrastructure/
│   ├── Authorization/                   ← طبقة التفويض كاملة (ADR-006)
│   │   ├── Policy.php                   (abstract)
│   │   ├── Decision.php
│   │   ├── InvariantRegistry.php
│   │   ├── PermissionBuilder.php
│   │   └── TenantBoundary.php
│   ├── Tenancy/
│   │   ├── TenantContext.php            (implements Contracts\TenantContext)
│   │   └── TenantScope.php
│   ├── Persistence/
│   │   ├── BaseModel.php
│   │   └── Concerns/
│   │       └── BelongsToTenant.php
│   ├── Filesystem/
│   │   ├── SettingsDrivenDiskResolver.php
│   │   └── TenantAwarePathGenerator.php
│   └── Logging/
│       └── ContextProcessor.php
│
└── Presentation/
    ├── Filament/
    │   ├── Concerns/
    │   └── Components/
    └── Http/
        └── Middleware/
            ├── InitializeTenantContext.php
            ├── AssignRequestContext.php
            ├── SetLocale.php
            └── SecurityHeaders.php
```

### قاعدة الاستيراد لكل طبقة

| الطبقة | مسموح يستورد | ممنوع |
|---|---|---|
| `Support\Domain` | PHP نفسه، و`Support\Domain` | **أي حاجة** من `Illuminate` أو `Filament` أو `Livewire` |
| `Support\Application` | `Support\Domain`، عقود PHP | `Filament`، `Livewire`، `Illuminate\Http` |
| `Support\Infrastructure` | كل اللي فوق + `Illuminate` + باكدجات | منطق أعمال |
| `Support\Presentation` | كل اللي فوق + `Filament` | منطق أعمال، استعلامات معقّدة |

> اختبارات معمارية بتفرض الجدول ده — `docs/01` و`docs/17` بند ٣.

---

## ٢. ترتيب البناء

```
الخطوة ١  Domain\Exceptions + Domain\ValueObjects     ← مفيش اعتماديات
الخطوة ٢  Application\Contracts                       ← واجهات بس
الخطوة ٣  Infrastructure\Tenancy                      ← TenantContext + TenantScope
الخطوة ٤  Infrastructure\Persistence                  ← BelongsToTenant (بيحتاج ٣)
الخطوة ٥  Infrastructure\Authorization                ← بيحتاج ٣ و٤
الخطوة ٦  Presentation\Http\Middleware                ← بيحتاج ٣
الخطوة ٧  التسجيل في ServiceProviders                 ← بيحتاج كل اللي فوق
```

> **مفيش خطوة بتبدأ قبل ما اختبارات اللي قبلها تعدّي.** الطبقة دي بيقف عليها كل حاجة تانية،
> وباگ فيها بيظهر كباگ في مكان تاني خالص بعد أسبوعين.

---

## ٣. الخطوة ١ — `Domain`

### `MissingTenantContextException`

```php
namespace Src\Support\Domain\Exceptions;

final class MissingTenantContextException extends DomainException
{
    public function __construct(public readonly string $modelClass)
    {
        parent::__construct("استعلام على {$modelClass} من غير سياق مستأجر.");
    }
}
```

> ⚠️ الرسالة دي **للمطوّر مش للمستخدم** — عشان كده مش من `__()`. دي استثناء بيوقف التنفيذ،
> ومابيوصلش للواجهة أبداً. أي استثناء **بيتعرض للمستخدم** لازم يكون مترجم (`docs/18` بند ٨).

**خلصت لما:** الاستثناء بيترمى ورسالته فيها اسم الموديل.

---

## ٤. الخطوة ٢ — `Application\Contracts`

```php
namespace Src\Support\Application\Contracts;

interface TenantContext
{
    /** المستأجر الحالي، أو null لو مفيش سياق */
    public function id(): ?int;

    /** يضبط السياق صراحةً — بيقبل null كقيمة مقصودة */
    public function set(?int $tenantId): void;

    /** يرجّع لسلوك «خد المستأجر من Filament» (ADR-008) */
    public function forget(): void;

    /** هل إحنا جوه withoutScope حالياً؟ TenantScope بيسأل عليها */
    public function isBypassed(): bool;

    /** قراءة عبر كل المستأجرين — بشروط docs/20 بند ٣-٧ */
    public function withoutScope(callable $callback): mixed;

    public function forEachTenant(callable $callback): void;
}
```

> **الواجهة في `Application` والتنفيذ في `Infrastructure`.** ده بيخلّي `BelongsToTenant`
> يعتمد على تجريد مش على كلاس فيه `Filament::getTenant()`.

**خلصت لما:** الواجهة موجودة ومحدش بيستورد `Infrastructure\Tenancy\TenantContext` مباشرة
غير الـ ServiceProvider.

---

## ٥. الخطوة ٣ — `Infrastructure\Tenancy`

الكود الكامل في **`docs/03` بند ٣**. العقد هنا:

| العنصر | العقد |
|---|---|
| التسجيل | **singleton إلزامي** — `bind` عادي بيدّي نسخة فاضية كل مرة والعزل بيقع بصمت |
| `id()` | بتحترم `set(null)` كقيمة مقصودة عبر فلاج `isSet` (ADR-008) |
| `forget()` | بترجّع لسلوك Filament — مش نفس `set(null)` |
| `forEachTenant()` | بتجيب القايمة بـ `->get()->all()` **جوه** الـ bypass، مش `cursor()` بره منه |
| `syncDependents()` | `setPermissionsTeamId()` + `forgetCachedPermissions()` + `Log::shareContext()` — **ومفيش** `config(['cache.prefix'])` |

### `TenantScope`

```php
public function apply(Builder $builder, Model $model): void
{
    $context  = app(TenantContext::class);
    $tenantId = $context->id();

    if ($tenantId === null) {
        // الافتراضي = الرفض. من غير سياق بنرمي، مانرجّعش كل الصفوف.
        if (! $context->isBypassed()) {
            throw new MissingTenantContextException($model::class);
        }

        return;
    }

    $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
}
```

**خلصت لما:**

```php
it('يرمي استثناء بدل ما يرجّع كل الصفوف بدون سياق');
it('set(null) يمسح السياق فعلاً حتى داخل طلب لوحة');       // ADR-008
it('forEachTenant يقرأ الصفوف والـ bypass لسه مفتوح');      // ADR-008
it('forEachTenant يرجّع السياق الأصلي في النهاية');
```

---

## ٦. الخطوة ٤ — `BelongsToTenant`

الكود في **`docs/03` بند ٣**. العقد:

| البند | القاعدة |
|---|---|
| مين بيستخدمه | كل موديل فيه عمود `tenant_id` |
| مين **مابيستخدموش** | `Tenant` (بيعرّف الحد) و`User` (بيعبر الحد) — ADR-002 |
| عند الإنشاء | بيحط `tenant_id` تلقائياً؛ من غير سياق بيرمي |
| `$fillable` | `tenant_id` **مايدخلش** — الـ trait هو اللي بيحطه |

**خلصت لما:** الاختبار التلقائي في `docs/03` بند ٥ بيعدّي — بيمشي على كل موديل فيه
`tenant_id` ويتأكد إن الـ trait موجود.

---

## ٧. الخطوة ٥ — `Infrastructure\Authorization`

الكود الكامل في **`docs/19`**. الخلاصة التنفيذية:

| الكلاس | المسؤولية | المرجع |
|---|---|---|
| `Policy` (abstract) | `decide()` / `decideFor()` / `invariants()` / `permissionFor()` | `19` بند ٣ |
| `Decision` | سلسلة الفحص: `withinTenant` → `permission` → `rule` | `19` بند ٣ |
| `TenantBoundary` | `crosses($argument): bool` | `19` بند ٥ · ADR-005 |
| `InvariantRegistry` | `guards($ability, $argument): bool` | `19` بند ٥ |
| `PermissionBuilder` | `allPermissionNames()` · `expandPatterns()` · `groups()` | `02` بند ٣ |

### العقود اللي مالهاش تنازل

١. **`Decision::permission()` بتستخدم `config('authorization.guard')`** — مش `filament()`.
   الـ Policy بتتنادى من Job و Command و API و Test، ومفيش لوحة مبنية هناك. (ADR-006)

٢. **دالة بتاخد سجل → `decideFor()`. دالة بتاخد كلاس أو ولا حاجة → `decide()`.** (ADR-007)

٣. **كل دالة بترجّع `Response`** — مش `bool`. `bool` بيضيّع سبب الرفض.

٤. **`Gate::before` بيرجّع `null` مش `true`** لقواعد السلامة **ولحدود المستأجر**.
   (ADR-005 · `19` بند ٥)

٥. **`expandPatterns()` بتقسم على أول فاصل بس** — الشق الشمال فعل، اليمين مورد.
   مفيش تخمين. (ADR-001)

**خلصت لما:** كل الاختبارات المعمارية في `docs/19` بند ٩ بتعدّي، ومصفوفة الأدوار في بند ١٠.

---

## ٨. الخطوة ٦ — الـ Middleware

| الكلاس | بيعمل إيه | ترتيبه |
|---|---|---|
| `InitializeTenantContext` | `TenantContext::set(Filament::getTenant()?->getKey())` | **الأول** — كل اللي بعده بيعتمد عليه |
| `AssignRequestContext` | `request_id` + `user_id` + `tenant_id` في `Log::shareContext()` | بعد المستأجر |
| `SetLocale` | لغة المستخدم → `App::setLocale()` + `Carbon::setLocale()` | بعد المصادقة |
| `SecurityHeaders` | الرؤوس الأمنية (`docs/12` بند ٦) | الآخر |

> ⚠️ **الترتيب مش تفصيلة.** `AssignRequestContext` بيسجّل `tenant_id` — لو اشتغل قبل
> `InitializeTenantContext`، كل سطر لوج في الطلب هيبقى `tenant_id: null`، وساعتها تتبّع أي
> مشكلة في الإنتاج بيبقى مستحيل. وده بالظبط اللي `docs/11` بيحذّر منه.

التسجيل: الميدلوير بتوع المستأجر في `->tenantMiddleware([...], isPersistent: true)`،
والباقي في `->middleware([...], isPersistent: true)` على اللوحة.

---

## ٩. الخطوة ٧ — التسجيل

`AppServiceProvider::register()`:

```php
// ⚠️ العقد alias على المحسوس — مش العكس. المقلوب بيعمل تكرار لا نهائي
//    في الـ container، لأن alias($abstract, $alias) بيسجّل
//    aliases[$alias] = $abstract. (ADR-015)
$this->app->singleton(TenantContext::class);
$this->app->alias(TenantContext::class, TenantContextContract::class);

$this->app->singleton(DiskResolver::class, SettingsDrivenDiskResolver::class);
$this->app->singleton(InvariantRegistry::class);
$this->app->singleton(TenantBoundary::class);
$this->app->singleton(PermissionBuilder::class);
```

`AuthorizationServiceProvider::boot()` — بالترتيب ده بالظبط:

```php
// ١. اكتشاف الـ Policies (docs/19 بند ٦)
Gate::guessPolicyNamesUsing(...);

// ٢. Gates لكل أسماء الكتالوج (docs/19 بند ٧ · ADR-003)
foreach (app(PermissionBuilder::class)->allPermissionNames() as $ability) {
    Gate::define($ability, ...);
}

// ٣. تجاوز المدير العام — بيحترم قواعد السلامة وحدود المستأجر
//    (docs/19 بند ٥ · ADR-005)
Gate::before(...);
```

> **ليه الترتيب ده؟** `Gate::before` بيتنفّذ قبل أي Gate معرّف، فالتسجيل ممكن يبقى بأي ترتيب
> تقنياً — بس الترتيب ده بيقرا زي المسار الفعلي في `docs/19` بند ١٢، فمراجعة الكود بتبقى أسهل.

**خلصت لما:**

```php
it('TenantContext مسجّل كـ singleton', function () {
    expect(app(TenantContext::class))->toBe(app(TenantContext::class));
});

it('كل صلاحية في الكتالوج لها Gate معرّف');       // ADR-003
it('canAccessPanel يعمل لدور admin');             // ADR-003 — المصيدة الأصلية
```

---

## ١٠. معايير القبول للطبقة كلها

- [ ] الشجرة في بند ١ مطابقة حرفياً — مفيش `Authorization` تحت `Domain` (ADR-006)
- [ ] `Src\Support\Domain` صفر استيراد من `Illuminate` أو `Filament` — اختبار معماري بيثبت
- [ ] `TenantContext` singleton — اختبار بيثبت
- [ ] `set(null)` بيمسح فعلاً · `forget()` بترجّع لـ Filament (ADR-008)
- [ ] `forEachTenant()` بيقرا جوه الـ bypass وبيرجّع السياق (ADR-008)
- [ ] مفيش `config(['cache.prefix'])` في أي مكان (ADR-008)
- [ ] استعلام من غير سياق بيرمي `MissingTenantContextException`
- [ ] `Decision::permission()` بيستخدم `config('authorization.guard')` (ADR-006)
- [ ] `decideFor()` موجودة وكل Policy بتاخد سجل بتستخدمها (ADR-007)
- [ ] كل صلاحية في الكتالوج لها Gate · `access.panel.admin` شغّالة (ADR-003)
- [ ] `Gate::before` بيحترم `invariants()` **و** `TenantBoundary` (ADR-005)
- [ ] ترتيب الميدلوير: المستأجر قبل اللوج
- [ ] كل الاختبارات المعمارية في `docs/01` و`docs/19` بند ٩ بتعدّي

---

## ١١. اللي **مش** في الطبقة دي

عشان محدش يوسّعها من غير قصد:

| مش هنا | مكانه |
|---|---|
| موديلات الأعمال | `src/Contexts/<Name>/Domain/Models/` |
| Policies السياقات | `src/Contexts/<Name>/Infrastructure/Policies/` |
| موارد Filament | `src/Contexts/<Name>/Presentation/Filament/` |
| كلاسات الإعدادات | `src/Contexts/Settings/Domain/Settings/` |
| اشتراكات وفواتير المؤسسات | `src/Contexts/Tenancy/` (سياق لسه ماتعملش) |

> `Support` فيه **الآليات** بس. أول ما تلاقي نفسك بتكتب قاعدة أعمال هنا — هي مش مكانها هنا.
