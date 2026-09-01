# ٢٣ — الشريحة الرأسية الأولى: `Identity`

> **الهدف مش «نبني إدارة مستخدمين».** الهدف إننا **نثبت أو نكسر** العقود في `docs/19`
> و`docs/20` وهي لسه رخيصة التغيير — قبل ما أي سياق تاني يتبني فوقها.
>
> لو الشريحة دي كشفت تناقض، ده **نجاح** مش فشل. تناقضين اتكشفوا وإحنا بنكتب المواصفة دي
> (ADR-011 و ADR-012) من غير ما نكتب سطر كود.

**اقرا الأول:** `docs/21` (القرارات — الحكم) · `docs/22` (عقد `Support`) · `docs/19` · `docs/20`

---

## ١. النطاق — بالظبط

### داخل الشريحة ✅

| العنصر | المطلوب |
|---|---|
| النواة المشتركة | `Tenant` model + migration + factory (ADR-011) |
| سياق `Identity` | `User` model + migration + factory + `IdentityServiceProvider` |
| العضوية | جدول `tenant_user` + علاقة many-to-many (ADR-002) |
| الصلاحيات | `config/authorization.php` بمورد `users` **بس** + `authorization:sync` |
| التفويض | `UserPolicy` واحدة كاملة — بكل دوالها |
| طبقة `Support` | خطوات ١–٧ من `docs/22` |
| الواجهة | `UserResource` — جدول + عرض + تعديل |
| اللوحة | `AdminPanelProvider` واحدة، تعدد مستأجرين شغّال |
| الترجمة | `ar` + `en` للمورد وللصلاحيات ولرسائل الرفض |
| الاختبارات | البند ٦ تحت — على PostgreSQL حقيقي |

### خارج الشريحة ❌

مش لأنها مش مهمة — لأنها **مابتختبرش عقد**:

الأدوار والصلاحيات كشاشة · الوسائط · الإعدادات · الثيم · الإشعارات · الطوابير · 2FA ·
الانتحال · الأجهزة · سجل النشاط · البحث الشامل · التصدير · الأونبوردنج · أي سياق تاني ·
أي مورد تاني · Horizon · Pulse · Telescope · Sentry · النسخ الاحتياطي

> **قاعدة الحسم:** «هل البند ده هيثبت أو يكسر عقد في `docs/19` أو `docs/20`؟»
> لأ → بره الشريحة. ومفيش استثناء «بس هي دقيقتين».

---

## ٢. بنية المشروع المستهدفة

```
fc-admin-template/
├── app/
│   ├── Console/Commands/SyncAuthorizationCommand.php
│   └── Providers/
│       ├── AppServiceProvider.php
│       ├── AuthorizationServiceProvider.php
│       └── Filament/AdminPanelProvider.php
├── config/
│   └── authorization.php
├── database/migrations/          ← ميجريشنز Laravel الأساسية بس
├── lang/{ar,en}/
│   ├── authorization.php
│   └── common.php
├── src/
│   ├── Support/                  ← docs/22
│   │   ├── Domain/
│   │   │   ├── Models/Tenant.php                    (ADR-011)
│   │   │   └── Exceptions/MissingTenantContextException.php
│   │   ├── Application/Contracts/TenantContext.php
│   │   └── Infrastructure/
│   │       ├── Authorization/{Policy,Decision,InvariantRegistry,PermissionBuilder,TenantBoundary}.php
│   │       ├── Tenancy/{TenantContext,TenantScope}.php
│   │       ├── Persistence/Concerns/BelongsToTenant.php
│   │       └── Database/Migrations/    ← tenants, tenant_user
│   └── Contexts/Identity/
│       ├── Domain/Models/User.php
│       ├── Infrastructure/Policies/UserPolicy.php
│       ├── Presentation/Filament/Resources/UserResource.php
│       │   └── UserResource/Pages/{ListUsers,EditUsers,ViewUser}.php
│       ├── Database/{Migrations,Factories}/
│       ├── Lang/{ar,en}/identity.php
│       ├── Tests/
│       └── IdentityServiceProvider.php
└── tests/
    ├── Architecture/{LayersTest,AuthorizationTest}.php
    ├── Feature/Identity/
    ├── Pest.php
    └── TestCase.php
```

> **مفيش `src/Contexts/Tenancy/`.** `Tenant` نواة مشتركة في `Support` (ADR-011)،
> وسياق `Tenancy` هيتعمل بعدين للاشتراكات وإدارة المؤسسات — مش دلوقتي.

---

## ٣. حدود السياق واتجاه الاعتماد

```
        ┌─────────────────────────┐
        │   Contexts\Identity     │
        │  Domain → App → Infra   │
        │        → Presentation   │
        └───────────┬─────────────┘
                    │  (اتجاه واحد)
                    ▼
        ┌─────────────────────────┐
        │        Support          │
        │   Tenant · TenantContext│
        │   Policy · Decision     │
        └─────────────────────────┘
                    │
                    ▼
                (مفيش حاجة)
```

**أربع قواعد بيفرضها اختبار:**

| # | القاعدة | الاختبار |
|---|---|---|
| ١ | `Support` مابيستوردش أي سياق | `arch('Support لا يستورد أي سياق')` |
| ٢ | السياقات مابتستوردش بعضها | `arch('السياقات لا تستورد بعضها')` |
| ٣ | `Domain` واجهات أيوه، إطار لأ (ADR-012) | `arch('طبقة Domain: واجهات أيوه، إطار لأ')` |
| ٤ | `Application` مابيعرفش الواجهة | `arch('طبقة Application لا تعرف الواجهة')` |

القاعدة ١ هي **الجديدة** والأهم — هي اللي كانت مكسورة قبل ADR-011 ومحدش واخد باله.

---

## ٤. حدود التفويض

`UserPolicy` هي الاختبار الحقيقي لـ `docs/19`. لازم تغطي **كل** نمط:

| الدالة | المدخل | بتثبت إيه |
|---|---|---|
| `viewAny(User $user)` | `decide()` | قدرة بلا سجل |
| `create(User $user)` | `decide()` | قدرة بلا سجل |
| `view(User $u, User $target)` | `decideFor()` | حارس المستأجر (ADR-007) |
| `update(User $u, User $target)` | `decideFor()` | صلاحية + قاعدة أعمال |
| `delete(User $u, User $target)` | `decideFor()` | **قاعدة سلامة:** مينفعش تحذف نفسك |

```php
final class UserPolicy extends Policy
{
    protected function resource(): string
    {
        return 'users';
    }

    /** قواعد سلامة المدير العام مابيتخطاهاش (docs/19 بند ٥) */
    public function invariants(): array
    {
        return ['delete'];
    }

    public function viewAny(User $user): Response
    {
        return $this->decide()->permission($user, $this, 'view_any')->response();
    }

    public function create(User $user): Response
    {
        return $this->decide()->permission($user, $this, 'create')->response();
    }

    public function view(User $user, User $target): Response
    {
        return $this->decideFor($target)
            ->permission($user, $this, 'view')
            ->response();
    }

    public function update(User $user, User $target): Response
    {
        return $this->decideFor($target)
            ->permission($user, $this, 'update')
            ->rule(! $target->trashed(), 'record_trashed')
            ->response();
    }

    public function delete(User $user, User $target): Response
    {
        return $this->decideFor($target)
            ->permission($user, $this, 'delete')
            ->rule($user->isNot($target), 'self_target')   // ← قاعدة سلامة
            ->response();
    }
}
```

> ⚠️ **ملاحظة على `decideFor()` مع `User`:** `User` مش تابع لمستأجر (ADR-002)، فحارس المستأجر
> بيعدّي من غير فحص. الشكل بيفضل موحّد عشان الاختبار المعماري بيفرضه على كل دالة بتاخد سجل،
> وعشان لو الموديل بقى تابع لمستأجر بكرة، الحارس يبقى موجود أصلاً.

**عزل المستخدمين بيتحقق بالعلاقة مش بالـ scope** (ADR-002):

```php
// UserResource::getEloquentQuery()
return parent::getEloquentQuery()
    ->whereHas('tenants', fn ($q) => $q->whereKey(app(TenantContext::class)->id()));
```

> ⚠️ ده **أهم سطر في الشريحة**. `User` مالوش global scope، فالعزل هنا **يدوي وصريح**.
> الاختبار في البند ٦-هـ بيثبته، والاختبار ده هو اللي بيفرّق بين شريحة شغّالة وشريحة مسرّبة.

---

## ٥. حدود سياق المستأجر

| الحد | السلوك المطلوب |
|---|---|
| مفيش سياق | استعلام على موديل تابع لمستأجر **بيرمي** `MissingTenantContextException` |
| `set(null)` | بيمسح فعلاً — حتى جوه طلب لوحة (ADR-008) |
| `forget()` | بيرجّع لسلوك «خد المستأجر من Filament» |
| عبور الحد | 404 مش 403 (ADR-005) |
| المدير العام | **مش** استثناء — نفس الحدود (ADR-005) |
| `User` | مالوش `tenant_id` ومالوش `BelongsToTenant` (ADR-002) |
| `Tenant` | مالوش `BelongsToTenant` — هو بيعرّف الحد |
| التسجيل | `TenantContext` **singleton** إلزامي |

> **الشريحة فيها موديل واحد بس تابع لمستأجر.** عشان نختبر `BelongsToTenant` و`TenantScope`
> فعلياً، بنستخدم `tenant_user` كجدول محوري وبنختبر العزل عليه — من غير ما نعمل موديل أعمال
> جديد بره النطاق.

---

## ٦. الاختبارات اللي لازم تعدّي

### أ. اختبارات معمارية — `tests/Architecture/`

```php
arch('Support لا يستورد أي سياق')                    // ← ADR-011، القاعدة الجديدة
    ->expect('Src\Support')->not->toUse('Src\Contexts');

arch('طبقة Domain: واجهات أيوه، إطار لأ')            // ← ADR-012
    ->expect('Src\Contexts\*\Domain')
    ->not->toUse([
        'Filament\Facades', 'Filament\Resources', 'Filament\Forms', 'Filament\Tables',
        'Illuminate\Http', 'Illuminate\Support\Facades', 'Livewire',
    ]);

arch('طبقة Support\Domain نظيفة')                    // ← ADR-006
    ->expect('Src\Support\Domain')
    ->not->toUse(['Filament\Facades', 'Illuminate\Http', 'Illuminate\Support\Facades']);

arch('كل Policy ترث الكلاس الأساسي')
    ->expect('Src\Contexts\*\Infrastructure\Policies')
    ->toExtend(Src\Support\Infrastructure\Authorization\Policy::class);
```

زائد اختبارات التفويض من `docs/19` بند ٩ — كلها إلزامية في الشريحة:
`hasPermissionTo` · `hasRole` · `can('x.y')` في PHP · `@can` في Blade · `skipAuthorization` ·
`decideFor`.

### ب. سلامة الكتالوج

```php
it('كل صلاحية في الكتالوج لها Gate معرّف');              // ADR-003
it('كل صلاحية مترجمة ar و en');                          // permission_label — ADR-003
it('*.users توسّع لكل أفعال users فقط');                 // ADR-001
```

### ج. الـ Policy — مصفوفة الأدوار

```php
dataset('user_matrix', [
    ['super_admin', 'viewAny', true],  ['super_admin', 'create', true],
    ['admin',       'viewAny', true],  ['admin',       'create', true],
    ['editor',      'viewAny', true],  ['editor',      'create', false],
    ['viewer',      'viewAny', true],  ['viewer',      'create', false],
]);
```

### د. قواعد السلامة

```php
it('لا يستطيع أي مستخدم حذف نفسه', function (string $role) {
    $user = userWithRole($role);
    expect($user->can('delete', $user))->toBeFalse("الدور {$role} قدر يحذف نفسه");
})->with(['super_admin', 'admin']);
```

> ده **الاختبار اللي بيثبت `Gate::before` مظبوط**. لو المدير العام قدر يحذف نفسه،
> يبقى `invariants()` مش شغّال — والقالب كله بيقف على النقطة دي.

### هـ. عزل المستأجر — **جوهر الشريحة**

```php
it('لا يرى المستخدم إلا أعضاء مؤسسته', function () {
    [$a, $b] = Tenant::factory()->count(2)->create();

    User::factory()->count(2)->hasAttached($a)->create();
    User::factory()->count(5)->hasAttached($b)->create();
    $shared = User::factory()->hasAttached([$a, $b])->create();

    app(TenantContext::class)->set($a->id);
    actingAs(userWithRole('admin', $a));

    $rows = livewire(ListUsers::class)->instance()->getTableRecords();

    expect($rows->pluck('id'))
        ->toContain($shared->id)
        ->not->toContain(...User::whereHas('tenants', fn ($q) => $q->whereKey($b->id))
            ->whereDoesntHave('tenants', fn ($q) => $q->whereKey($a->id))
            ->pluck('id')->all());
});

it('لا يتجاوز أي دور حدود المستأجر', function (string $role) { /* 404 */ })
    ->with(['super_admin', 'admin', 'editor', 'viewer']);        // ADR-005

it('يرمي استثناء بدل ما يرجّع كل الصفوف بدون سياق');
it('لا تتسرّب الأدوار بين المستأجرين');                          // teams => true
```

### و. الواجهة

```php
it('يمنع viewer من فتح صفحة الإنشاء بالـ URL المباشر');
it('يخفي زرار الحذف عمّن لا يملك الصلاحية');
it('صفحة الجدول لا تتجاوز ١٠ استعلامات');
it('admin يقدر يفتح اللوحة');                                    // ADR-003 — المصيدة الأصلية
```

---

## ٧. متطلبات PostgreSQL

> **الاختبارات على PostgreSQL حقيقي. مفيش SQLite ومفيش `:memory:`.** (`docs/17` بند ١)

السبب مش أيديولوجي — تلات سلوكيات بتفرق فعلياً في الشريحة دي:

| السلوك | SQLite | PostgreSQL |
|---|---|---|
| أعمدة `json` | نص | نوع فعلي بمعاملات وفهارس |
| قيود الـ FK | معطّلة افتراضياً | مفروضة — `tenant_user` بيعتمد عليها |
| المفتاح الأساسي المركّب | متساهل | صارم |

```xml
<!-- phpunit.xml -->
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="DB_CONNECTION" value="pgsql"/>
    <env name="DB_DATABASE" value="fc_admin_testing"/>
    <env name="CACHE_STORE" value="array"/>
    <env name="SESSION_DRIVER" value="array"/>
    <env name="QUEUE_CONNECTION" value="sync"/>
    <env name="MAIL_MAILER" value="array"/>
    <env name="PERMISSION_CACHE_STORE" value="array"/>
</php>
```

### قواعد إلزامية

١. **`RefreshDatabase`** على كل الاختبارات — الشريحة بتغيّر حالة عامة (سياق مستأجر، كاش صلاحيات).

٢. **`TenantContext` بيترجّع لحالته بين كل اختبار.** هو singleton بحالة قابلة للتغيير —
   اختبار سايب سياق مضبوط بيلوّث اللي بعده، والفشل بيظهر في اختبار تاني خالص:

   ```php
   // tests/Pest.php
   beforeEach(function () {
       app(TenantContext::class)->forget();
       app(PermissionRegistrar::class)->forgetCachedPermissions();
   });
   ```

   > البند ده مش تفصيلة. من غيره، اختبارات العزل بتعدّي أو تفشل حسب **ترتيب التشغيل** —
   > وده أسوأ نوع اختبار: بيدّي ثقة كاذبة.

٣. **`--parallel` بيحتاج قاعدة لكل عملية.** Laravel بيعمل `fc_admin_testing_1..N` تلقائياً.
   تأكد إن مستخدم Postgres معاه `CREATEDB`.

٤. **`PERMISSION_CACHE_STORE=array`** — من غيرها الاختبارات بتتلوّث من بعض عبر Redis.

---

## ٨. معايير قبول الشريحة

الشريحة **مش خالصة** غير لما **كل** ده يتحقق:

> ✅ **اتقفلت في ٣١ أغسطس ٢٠٢٦.** النتيجة عقد-بعقد بالدليل في **بند ١٠**.
> البند الوحيد الفاضل هو اللقطات، وهو مؤجّل لمهمة الواجهة.

### البنية
- [x] الشجرة في بند ٢ مطابقة — مفيش `src/Contexts/Tenancy/` (ADR-011)
- [x] `Tenant` في `Src\Support\Domain\Models\` (ADR-011)
- [x] الأربع قواعد المعمارية في بند ٣ بتعدّي كاختبارات
- [x] `composer lint` أخضر (Pint + PHPStan level 6)

### التفويض
- [x] `UserPolicy` بكل دوالها، كلها بترجّع `Response` (`docs/19`)
- [x] الدوال اللي بتاخد سجل بتستخدم `decideFor()` (ADR-007)
- [x] `Gate::before` بيحترم `invariants()` **و** `TenantBoundary` (ADR-005)
- [x] كل صلاحية في الكتالوج لها Gate · `access.panel.admin` شغّالة (ADR-003)
- [x] صفر `hasPermissionTo` بره `Decision` · صفر `can('x.y')` · صفر `skipAuthorization`

### العزل
- [x] `UserResource::getEloquentQuery()` بيفلتر بالعضوية (بند ٤)
- [x] كل الأدوار — بما فيهم `super_admin` — بياخدوا 404 على سجل مستأجر تاني (ADR-005)
- [x] استعلام بدون سياق بيرمي مش بيرجّع صفوف
- [x] الأدوار معزولة بين المستأجرين (`teams => true`)

### الاختبارات
- [x] كل اختبارات بند ٦ خضراء **على PostgreSQL**
- [x] `TenantContext` بيترجّع في `beforeEach` (بند ٧-٢)
- [x] الاختبارات بتعدّي بـ `--parallel` وبترتيب عشوائي
- [x] صفحة الجدول ≤ ١٠ استعلامات

### الواجهة والترجمة
- [x] `admin` بيفتح اللوحة · `viewer` بياخد 403 على `create`
- [x] صفر نص مكتوب · صفر `left`/`right` · صفر لون مباشر
- [ ] لقطات: عربي فاتح · عربي داكن · إنجليزي — **مؤجّلة** (محتاجة متصفّح؛ بند ١٠)

### البوابة النهائية
- [x] **مراجعة عقود:** كل عقد في `docs/19` و`docs/20` اتعلّم عليه **اتثبت** أو **اتكسر**
- [x] أي عقد اتكسر → ADR جديد في `docs/21` **قبل** ما نكمّل

> **البند الأخير هو سبب وجود الشريحة.** لو خلصت من غير ما تتعلّم منها حاجة، يبقى غالباً
> ماختبرتش العقود فعلاً.

---

## ٩. ترتيب التنفيذ

```
١. تحقّق من إصدارات الباكدجات (composer show)      ← البند المفتوح الوحيد
٢. سكافولد Laravel + Filament + Docker + Postgres
٣. Support خطوات ١–٤ (docs/22)  +  Tenant + tenant_user
٤. Support خطوات ٥–٧            +  config/authorization.php + sync
٥. Identity: User + migration + factory + provider
٦. UserPolicy + الترجمة + رسائل الرفض
٧. الاختبارات المعمارية واختبارات الـ Policy        ← قبل الواجهة
٨. AdminPanelProvider + UserResource
٩. اختبارات العزل والواجهة
١٠. مراجعة العقود (بند ٨ — البوابة النهائية)
```

> **الخطوة ٧ قبل ٨ مقصودة.** لو الواجهة اتبنت الأول، هتشتغل وشكلها صح — وعزل المستأجر مكسور
> ومحدش واخد باله. الاختبار بيسبق الشاشة هنا تحديداً لأن الفشل صامت.

---

## ١٠. مراجعة العقود — النتيجة (البوابة النهائية)

> **اتقفلت في ٣١ أغسطس ٢٠٢٦.** كل عقد في `docs/19` و`docs/20` و`docs/22` اتعلّم عليه
> **اتثبت** أو **اتكسر** أو **مااتجرّبش**. «مااتجرّبش» مكتوبة صراحةً — مش متسايبة كفراغ.

**الأرقام:** ٨٢ اختبار · ٢٢٩ تأكيد · **صفر `skip`** · على PostgreSQL 18.6 حقيقي ·
بيعدّي بالتوازي (٨ عمليات) وبترتيب عشوائي.

### عقود `docs/19` — التفويض

| العقد | النتيجة | الدليل |
|---|---|---|
| كل التفويض عبر Policies | ✅ اتثبت | صفر `can('x.y')` · صفر `skipAuthorization` · صفر `@can` باسم صلاحية |
| `hasPermissionTo` في مكانين بس | ✅ اتثبت | اختبار معماري على نص الكود |
| `hasRole` بره `Gate::before` ممنوع | ✅ اتثبت | اختبار معماري |
| كل دالة Policy بترجّع `Response` | ✅ اتثبت | فحص بالـ reflection على نوع الإرجاع |
| `decideFor()` لكل دالة بتاخد سجل (ADR-007) | ✅ اتثبت — **بعد تصليح الاختبار** | ⚠️ الادعاء الأصلي كان غير صحيح: الـ pattern كان مكسور (`[\w\]` في نص single-quoted → `[\w\]`) فمالقاش ولا دالة، والتأكيد نفسه كان بيستخدم `toContain()` بمعامل رسالة مش موجود. الاتنين اتصلّحوا في ٣١ أغسطس ٢٠٢٦ — الاختبار دلوقتي بيفحص **٤ دوال** (`view`, `update`, `delete`, `resetPassword`) وبيفشل لو أي واحدة سابت `decideFor()`، وفيه حارس ضد رجوعه فاضي |
| `Gate::before` بيحترم `invariants()` | ✅ اتثبت | `super_admin` و`admin` مش قادرين يحذفوا نفسهم |
| `Gate::before` بيحترم `TenantBoundary` (ADR-005) | ✅ اتثبت | **404** على HTTP لكل الأدوار الأربعة |
| `Gate::before` بيرجّع `null` لغير المدير العام | ✅ اتثبت | مصفوفة الأدوار (٨ حالات) |
| اكتشاف الـ Policies بره `App\Models` | ✅ اتثبت | الـ Policy بتتلاقى وبتشتغل |
| كل صلاحية لها Gate · `access.panel.admin` (ADR-003) | ✅ اتثبت | ١٤ صلاحية · الأدوار الأربعة بتفتح اللوحة |
| `->authorize()` على الإجراءات | ✅ اتثبت جزئياً | الإجراءات المفردة اتثبتت · `authorizeIndividualRecords()` **مااتجرّبش** |
| الشكل المعياري للحقل الحسّاس (ADR-009) | ✅ اتثبت — **وكشف نقص في ADR-009** | حقل كلمة المرور مربوط بـ `reset_password.users`. القياس أثبت إن `saved()` **مابتحميش** حقل قياسي بيحدّد `dehydrated()` → [ADR-017](21-decisions.md#adr-017) |
| مكان `TenantBoundary` | 🔴 **اتكسر** | `docs/19` كان بيقول `Tenancy/`، ADR-006 بيقول `Authorization/` → [ADR-015](21-decisions.md#adr-015) |

### عقود `docs/20` — الأمان

| العقد | النتيجة | الدليل |
|---|---|---|
| تسرّب عبر المستأجرين | ✅ اتثبت | فلتر العضوية · `TenantScope` بيرمي بلا سياق · 404 عبر الحد |
| تصعيد صلاحيات: زرار مخفي و endpoint مفتوح | ✅ اتثبت | `viewer`/`editor` بياخدوا **403** على `create` و`edit` بالـ URL المباشر |
| `$guarded = []` ممنوع | ✅ اتثبت | كل الموديلات بـ `$fillable`؛ `tenant_id` بره الـ fillable |
| XSS مخزّن من `RichEditor` | ⚪ **مش منطبق** | مفيش `RichEditor` في الشريحة |
| `whereRaw`/`orderByRaw` بمدخلات مستخدم | ⚪ **مش منطبق** | مفيش استعلام خام |
| رفع SVG لمجموعة عامة | ⚪ **مش منطبق** | مفيش وسائط في الشريحة |

### عقود `docs/22` و ADR-008 — طبقة `Support`

| العقد | النتيجة | الدليل |
|---|---|---|
| `TenantContext` singleton | ✅ اتثبت | اختبار تراجُع بعد ما [ADR-015](21-decisions.md#adr-015) صلّح التسجيل |
| `set(null)` بيمسح فعلاً | ✅ اتثبت | اختبار مباشر |
| `forget()` بيرجّع لسلوك Filament | ✅ اتثبت | اختبار مباشر |
| `forEachTenant()` بيقرا جوّه الـ bypass | ✅ اتثبت | بيشوف النشطين بس |
| `forEachTenant()` بيرجّع السياق | ✅ اتثبت | حالتين: بسياق وبغير سياق |
| مفيش `config(['cache.prefix'])` | ✅ اتثبت | مش موجودة في الكود |
| استعلام بلا سياق بيرمي | ✅ اتثبت | `MissingTenantContextException` |
| `Support` مابيستوردش `Contexts` (ADR-011) | ✅ اتثبت | اختبار معماري |
| نقاء `Domain` (ADR-012) | ✅ اتثبت | اختبار معماري للسياق و للـ Support |
| تسجيل `TenantContext` نفسه | 🔴 **اتكسر** | الشكل الموثّق بيعمل حلقة لا نهائية → [ADR-015](21-decisions.md#adr-015) |

### عقود الشريحة نفسها

| العقد | النتيجة | الدليل |
|---|---|---|
| `getEloquentQuery()` بيفلتر بالعضوية | ✅ اتثبت — **وأخطر مما كان مكتوب** | اتقاس إنه **الطبقة الوحيدة**؛ Filament مابيسجّلش global scope هنا → [ADR-016](21-decisions.md#adr-016) |
| الأدوار معزولة بين المستأجرين (`teams`) | ✅ اتثبت | نفس المستخدم `admin` في مستأجر وبلا صلاحية في التاني |
| صفحة الجدول ≤ ١٠ استعلامات | ✅ اتثبت | **٦** استعلامات مع ١٥ صف |
| `--parallel` وترتيب عشوائي | ✅ اتثبت | ٨ عمليات بقواعد منفصلة · بذرتين مختلفتين |
| صفر نص مكتوب · صفر CSS اتجاهي · صفر hex | ✅ اتثبت | اختبارات على القوالب والكود؛ صفحة `welcome` بتاعة السكافولد اتشالت |
| علاقة ملكية المستأجر عند Filament | 🔴 **اتكسر** | لوحة بـ `->tenant()` بترمي على موديل بلا علاقة `tenant` → [ADR-016](21-decisions.md#adr-016) |
| عضوية المستخدم عند الإنشاء | ✅ اتثبت | ربط تلقائي بمستأجر `TenantContext` في `CreateUser::afterCreate()`، جوّه معاملة. اختبار تراجُع بيفحص صف `tenant_user` والرؤية والعزل عن مستأجر تاني → [ADR-016](21-decisions.md#adr-016) |
| لقطات: عربي فاتح/داكن · إنجليزي | ❌ **ماتعملتش** | محتاجة متصفّح؛ مؤجّلة لمهمة الواجهة |

### الخلاصة

**ستة عقود اتكسروا أو كانوا ناقصين**، وكلهم اتسجّلوا في ADR قبل أي كمالة:
أربعة في [ADR-015](21-decisions.md#adr-015) واتنين في [ADR-016](21-decisions.md#adr-016).
كلهم **اتقفلوا** — مفيش عقد مكسور سايب.

**البند الأهم اللي اتعلمناه:** العقود اللي كانت «واضحة» في الوثائق هي اللي وقعت —
تسجيل الـ container، مسار كلاس، مونت Docker، وربط موديل المصادقة. مفيش واحد فيهم
اتكسر بسبب تعقيد؛ كلهم اتكسروا لأن **محدش نفّذهم مرة واحدة**.

ADR-013 راهن إن شريحة رأسية رفيعة هتكشف ده وهو لسه رخيص. الرهان كسب.

### اللي لسه مفتوح بعد الشريحة

١. **`authorizeIndividualRecords()`** — الإجراء الجماعي متكتوب صح ومااتجرّبش.
٢. **اللقطات** — مؤجّلة لمهمة الواجهة.
٣. **ميدلوير `AssignRequestContext` و`SetLocale` و`SecurityHeaders`** — مواصفاتهم في
   `docs/10` و`11` و`12`، بره غرض الشريحة.

> **الشكل المعياري للحقل الحسّاس اتقفل** في [ADR-017](21-decisions.md#adr-017) بعد ما
> حقل كلمة المرور اتضاف واتجرّب سلوكياً.

> **عضوية المستخدم عند الإنشاء اتقفلت** في [ADR-016](21-decisions.md#adr-016) —
> ماعادتش بند مفتوح.
