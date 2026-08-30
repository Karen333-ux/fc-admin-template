# ٢٠ — تعليمات الأمان

> الملف ده مش «نصايح». دي شروط قبول. أي بند مكسور = رفض PR.
>
> الأمان في `docs/12-auth-security.md` بيتكلم عن **مصادقة المستخدم** (2FA، الأجهزة، الانتحال).
> الملف ده بيتكلم عن **حماية التطبيق نفسه**.

---

## ١. نموذج التهديد — إيه اللي بنحمي منه

قبل أي كود، افهم إن التطبيق ده فيه **٤ حدود ثقة**:

| الحد | الافتراض |
|---|---|
| **المستخدم ← التطبيق** | كل مدخل من المتصفح **عدائي** لحد ما يتحقق. حتى من الأدمن. |
| **المستأجر ← المستأجر** | كل مستأجر بيحاول يشوف بيانات التاني (حتى لو من غير قصد — باگ بيكفي). |
| **التطبيق ← خدمة خارجية** | S3، البريد، الـ webhooks — كلهم ممكن يفشلوا أو يرجّعوا حاجة مش متوقعة. |
| **المطوّر ← الإنتاج** | أي سر في الكود = سر مسرّب. |

**أخطر ٣ سيناريوهات في تطبيقنا بالذات:**

1. **تسريب بين المستأجرين** — استعلام ناسي الـ scope. الأخطر لأنه صامت.
2. **تصعيد صلاحية** — زرار مخفي والـ endpoint وراه مفتوح.
3. **XSS مخزّن من محرّر النصوص** — الـ RichEditor بيخزّن HTML، والعرض الخام بيشغّله.

---

## ٢. التفويض — أخطر منطقة

القواعد الكاملة في `docs/19-policies.md`. الملخص الأمني:

| ❌ ثغرة | ✅ الصح |
|---|---|
| `shouldRegisterNavigation()` بس | + Policy على المورد |
| `->visible()` بس على إجراء حساس | + `->authorize()` |
| `->disabled()` على حقل حساس | + `->saved(false)` |
| فحص في الـ Resource بس | + `Gate::authorize()` في الـ Action |
| `->authorize('deleteAny')` على إجراء جماعي | `->authorizeIndividualRecords('delete')` |
| `Gate::before` بيرجّع `true` دايماً للمدير | + احترام قواعد السلامة |
| `->skipAuthorization()` | **ممنوع نهائياً** |

### الحقول الحساسة — الثغرة الأشهر

```php
// ❌ ثغرة: القيمة بتوصل في الـ request وبتتحفظ
Toggle::make('is_super_admin')->disabled();

// ❌ برضه ثغرة: الإخفاء مابيمنعش الحفظ
Toggle::make('is_super_admin')->visible(fn () => auth()->user()->can('grant', $record));

// ✅ إخفاء + منع حفظ
Toggle::make('is_super_admin')
    ->visible(fn (?User $record) => auth()->user()->can('grant', $record))
    ->saved(fn (?User $record) => auth()->user()->can('grant', $record));
```

**اكتب اختبار لكل حقل حساس** بيثبت إن القيمة مش بتتحفظ لو المستخدم مالوش الصلاحية:

```php
it('لا يحفظ is_pinned لمن لا يملك صلاحية التثبيت', function () {
    actingAsRole('editor');       // مالوش pin.announcements

    livewire(CreateAnnouncement::class)
        ->fillForm([...validData(), 'is_pinned' => true])
        ->call('create');

    expect(Announcement::latest('id')->first()->is_pinned)->toBeFalse();
});
```

---

## ٣. عزل المستأجرين — أخطر تسريب

الطبقات الأربع في `docs/03-multi-tenancy.md`. الشروط الأمنية:

1. **الافتراضي = الرفض.** استعلام من غير سياق مستأجر **بيرمي استثناء**، مش بيرجّع كل الصفوف.
2. **الوصول لسجل من مستأجر تاني = 404 مش 403.** «ممنوع» بتأكد إن السجل موجود — وده تسريب.

```php
->ruleOrNotFound($record->tenant_id === app(TenantContext::class)->id(), 'record_not_found')
```

3. **الـ Jobs بتاخد `tenantId` صريح** — مفيش اعتماد على سياق ضمني.
4. **الكاش معزول ببادئة لكل مستأجر** — وكاش الصلاحيات على وجه الخصوص (المصيدة في `docs/03`).
5. **مسارات الملفات معزولة** — `tenants/{id}/...` عبر `PathGenerator`.
6. **`withoutScope()` ممنوع** إلا في أوامر إدارية موثّقة بتعليق يشرح ليه.

### الاختبار الإلزامي

```php
it('لا يسرّب بيانات بين المستأجرين', function (string $model) { /* ... */ })
    ->with(allTenantOwnedModels());
```

الاختبار ده بيمرّ على **كل** موديل تلقائياً — يعني أي موديل جديد داخل في الاختبار من غير ما حد يفتكر.

---

## ٤. المدخلات — كل حاجة عدائية

### التحقق (Validation)

- **كل** مدخل بيتحقق على السيرفر. قواعد Filament في الـ Form **مش** كفاية لو فيه endpoint تاني.
- التحقق في **Form Request** أو في الـ DTO، مش متناثر.
- استخدم قواعد ضيقة: `Rule::enum()`, `Rule::in()`, `Rule::exists()` بدل `string`.

```php
// ❌ واسع
'status' => 'required|string',

// ✅ ضيّق
'status' => ['required', Rule::enum(AnnouncementStatus::class)],
'tenant_id' => ['required', Rule::exists('tenants', 'id')->where('is_active', true)],
```

### Mass Assignment

```php
// ❌ ممنوع منعاً باتاً
protected $guarded = [];

// ✅ قائمة بيضاء صريحة
protected $fillable = ['title', 'body', 'status'];
```

**`tenant_id` و`author_id` و أي حقل صلاحيات مايدخلش `$fillable` أبداً** — بيتحطوا برمجياً.

### IDOR

Route model binding + Policy = محمي. لكن أي `->findOrFail($request->input('id'))` بإيدك:

```php
// ❌ IDOR — أي id بيشتغل
$announcement = Announcement::findOrFail($data['id']);

// ✅ الـ Global Scope بيقيّده بالمستأجر، والـ Policy بتفحص القدرة
$announcement = Announcement::findOrFail($data['id']);
Gate::authorize('publish', $announcement);
```

السطرين شكلهم واحد — الفرق إن التاني فيه `Gate::authorize()`. **مفيش استعلام على سجل واحد من غير فحص قدرة بعده.**

---

## ٥. المخرجات — XSS

### Blade

```blade
{{ $announcement->title }}      {{-- ✅ آمن، بيهرب تلقائياً --}}
{!! $announcement->body !!}     {{-- ⚠️ خام — خطر --}}
```

**`{!! !!}` ممنوع** إلا على محتوى **منقّى** (sanitized) صراحةً.

### محرّر النصوص — الثغرة الحقيقية عندنا

`RichEditor` بيخزّن HTML. لو عرضته خام، أي حد معاه صلاحية الكتابة يقدر يحقن سكربت يشتغل عند **كل** من بيقرا.

**الحل: تنقية عند الحفظ وعند العرض (الاتنين).**

```php
// عند الحفظ — في الـ Action أو mutator على الموديل
protected function body(): Attribute
{
    return Attribute::set(fn (string $value) => app(HtmlSanitizer::class)->clean($value));
}
```

```blade
{{-- عند العرض --}}
{!! app(HtmlSanitizer::class)->clean($announcement->body) !!}
```

`HtmlSanitizer` بيلفّ مكتبة تنقية بقائمة وسوم بيضاء ضيقة:

```php
final class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'em', 'u', 's', 'ul', 'ol', 'li',
        'h2', 'h3', 'h4', 'blockquote', 'a', 'code', 'pre',
    ];

    private const ALLOWED_ATTRIBUTES = ['a' => ['href', 'title', 'target', 'rel']];
}
```

**قواعد إلزامية:**
- **قائمة بيضاء** للوسوم، مش سوداء
- `<script>`, `<iframe>`, `<object>`, `<embed>`, `<form>`, `<style>` ممنوعة
- كل `on*` attributes (`onclick`, `onerror`, ...) بتتشال
- `href` بـ `javascript:` أو `data:` بيتشال
- كل `<a>` خارجي بياخد `rel="noopener noreferrer"` و `target="_blank"`
- `<img src>` من نطاقاتنا بس

> ⚠️ **للتنفيذ:** المكتبات المطروحة هي `symfony/html-sanitizer` (جزء من Symfony، مصانة رسمياً) أو `ezyang/htmlpurifier` عبر `mews/purifier`. **قرار أسبوع ٣** بعد مقارنة عملية. تأكد من الإصدار والتوافق مع PHP 8.4 قبل التثبيت.

### CSP (سياسة أمن المحتوى)

طبقة الدفاع الأخيرة ضد XSS:

```php
'Content-Security-Policy' => implode('; ', [
    "default-src 'self'",
    "script-src 'self' 'nonce-{$nonce}'",
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
    "font-src 'self' https://fonts.gstatic.com",
    "img-src 'self' data: https://cdn.example.com",
    "connect-src 'self' wss://ws.example.com",
    "frame-ancestors 'none'",
    "base-uri 'self'",
    "form-action 'self'",
]),
```

> ⚠️ **الواقع:** Filament و Livewire بيستخدموا سكربتات inline، فـ `script-src 'self'` هيكسر اللوحة. الخطة:
> 1. ابدأ بـ `Content-Security-Policy-Report-Only` وسجّل المخالفات
> 2. اضبط الـ nonces أو الـ hashes للسكربتات المشروعة
> 3. حوّل للفرض بعد ما التقارير تنضف
>
> **مهمة أسبوع ٥.** متفرضش CSP من غير المرحلة دي — هتقضي يومين تدوّر على اللي كسر.

---

## ٦. قاعدة البيانات — حقن SQL

Eloquent والـ Query Builder بيستخدموا prepared statements. الخطر في المناطق الخام:

```php
// ❌ حقن مباشر
DB::select("SELECT * FROM users WHERE name = '{$request->name}'");
$query->orderByRaw($request->input('sort'));
$query->whereRaw("status = {$status}");

// ✅ معاملات مربوطة
DB::select('SELECT * FROM users WHERE name = ?', [$request->name]);
$query->whereRaw('status = ?', [$status]);

// ✅ ترتيب من مدخل مستخدم — قائمة بيضاء إجبارية
$allowed = ['name', 'created_at', 'status'];
$sort = in_array($request->input('sort'), $allowed, true) ? $request->input('sort') : 'created_at';
$query->orderBy($sort, $request->input('dir') === 'asc' ? 'asc' : 'desc');
```

> **أسماء الأعمدة والجداول مش بتتربط كمعاملات** — لازم قائمة بيضاء. دي أشهر ثغرة في الفلاتر المخصصة.

**في فلاتر Filament المخصصة:** أي `->query()` بتستقبل `$data` من المستخدم. اتعامل معاها كمدخل عدائي.

---

## ٧. رفع الملفات

أخطر مدخل في أي تطبيق.

```php
SpatieMediaLibraryFileUpload::make('document')
    ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png'])   // ١
    ->maxSize(10240)                                                      // ٢
    ->disk(fn () => app(DiskResolver::class)->for('documents'));
```

### القواعد

1. **قائمة بيضاء للـ MIME** — مش سوداء. ومحدّدة لكل مجموعة.
2. **التحقق على السيرفر** — الـ accept في المتصفح تجميلي. Laravel بيتحقق من الـ MIME الفعلي مش الامتداد بس، لكن أضف `File::types([...])` صراحةً.
3. **إعادة ترميز الصور** — أي صورة تتحوّل لـ WebP عبر التحويلات. ده بيشيل أي payload مدسوس في الـ EXIF.
4. **اسم الملف مايتستخدمش خام** — Media Library بيولّد المسار، ما تعملش `$file->getClientOriginalName()`.
5. **الملفات الخاصة مش على ديسك عام** — روابط مؤقتة بس.
6. **مفيش تنفيذ في مجلد الرفع** — لو التخزين محلي، اقفل PHP في المجلد على مستوى الويب سيرفر.
7. **حد أقصى للحجم** على مستوى التطبيق **و** الويب سيرفر (`client_max_body_size`).
8. **فحص فيروسات** للملفات الجاية من مستخدمين خارجيين (ClamAV في job) — لو التطبيق هيستقبل رفع من الطلاب/العملاء.

### SVG — حالة خاصة خطرة

SVG هو **XML بيقبل سكربتات**. رفع SVG وعرضه = XSS.

```php
// ❌ ممنوع في أي مجموعة عامة
->acceptsMimeTypes(['image/svg+xml'])
```

لو محتاجينه (لوجو مثلاً): نقّيه بنفس الـ `HtmlSanitizer` بقائمة وسوم SVG بيضاء، أو حوّله لـ PNG عند الرفع.

---

## ٨. الأسرار

- **مفيش سر في الكود.** ولا في التعليقات، ولا في الاختبارات، ولا في السيدرز.
- `.env` **مش** في Git. `.env.example` بقيم وهمية بس.
- أسرار الإعدادات الديناميكية (كلمة مرور SMTP، مفاتيح API) في `encrypted()` بتاعة spatie/settings.
- `APP_KEY` محفوظ في مدير أسرار (vault / متغيرات بيئة المنصة) — مش في ملف على السيرفر بس.
- **تدوير المفاتيح:** كل ٦ شهور، أو فوراً لو حد ساب الفريق أو حصل تسريب.
- **لو سر اتسرّب في Git:** غيّره فوراً. مسحه من التاريخ **مش** كفاية — اعتبره محروق.

### فحص آلي

```bash
composer require --dev icanhazstring/composer-unused   # اختياري
```

وأهم من ده: **gitleaks** أو **trufflehog** في الـ CI بيفحص كل PR على أسرار مسرّبة. **مهمة أسبوع ٥.**

---

## ٩. الجلسات و CSRF

```dotenv
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_ENCRYPT=true
SESSION_LIFETIME=120
```

- **تدوير معرّف الجلسة عند تسجيل الدخول** — Laravel بيعمله تلقائياً في `Auth::login()`. لو عملت مصادقة مخصصة، نادِ `$request->session()->regenerate()` بنفسك (منع session fixation).
- **CSRF** مفعّل افتراضياً على `web`. لو استثنيت route، **اكتب تعليق يشرح ليه** — والاستثناء الشرعي الوحيد هو webhooks بتتحقق بتوقيع.
- **Webhooks:** تحقق من التوقيع (HMAC) دايماً. مفيش webhook مقبول من غير تحقق.

---

## ١٠. تحديد المعدّل والحماية من التعداد

القواعد في `docs/12-auth-security.md`. الإضافات الأمنية:

### منع تعداد الحسابات (User Enumeration)

```php
// ❌ بيقول للمهاجم إن الإيميل ده موجود
'البريد ده مش مسجّل عندنا'

// ✅ رسالة موحّدة
'البيانات دي مش صحيحة'
```

نفس الكلام على «نسيت كلمة المرور» — الرسالة واحدة سواء الإيميل موجود أو لأ، والوقت المستغرق متقارب.

### حدود على العمليات الغالية

```php
RateLimiter::for('exports', fn (Request $r) => Limit::perHour(10)->by($r->user()->id));
RateLimiter::for('media-upload', fn (Request $r) => Limit::perMinute(20)->by($r->user()->id));
RateLimiter::for('search', fn (Request $r) => Limit::perMinute(60)->by($r->user()->id));
```

من غير كده، مستخدم واحد يقدر يوقّع السيرفر بتصديرات متتالية.

---

## ١١. البث والـ API

### قنوات البث

```php
// ❌ قناة عامة — أي حد يسمع
Broadcast::channel('announcements', fn () => true);

// ✅ خاصة ومقيّدة بالمستأجر
Broadcast::channel('tenant.{tenantId}.announcements', function (User $user, int $tenantId) {
    return $user->tenants()->whereKey($tenantId)->exists();
});
```

**كل قناة `private` أو `presence`. مفيش قناة عامة فيها بيانات مستخدمين.**

### توكنز الـ API

لو أضفنا API (Sanctum):

- التوكن له **قدرات (abilities)** محددة، مش وصول كامل
- التوكن له **تاريخ انتهاء**
- التوكن **مربوط بمستأجر واحد**
- شاشة للمستخدم يشوف توكنزه ويلغيها
- كل استخدام بيسجّل آخر مرة (`last_used_at`)
- **نفس الـ Policies** بتنطبق — مفيش مسار جانبي للتفويض

---

## ١٢. الاعتماديات

- **`composer audit`** و **`npm audit`** في الـ CI — الـ PR بيرسب على ثغرة عالية أو حرجة
- **Dependabot** أو **Renovate** مفعّل على الريبو
- مراجعة شهرية للتحديثات الأمنية
- **متضيفش باكدج من غير مناقشة** (`docs/18-conventions.md` بند ٦)
- باكدج آخر تحديث ليه سنة = علامة حمراء

---

## ١٣. التسجيل والخصوصية

### ممنوع في اللوج

كلمات المرور · التوكنز · مفاتيح API · أرقام البطاقات · أرقام الهوية · محتوى الرسائل الخاصة · الـ session id

```php
'redact' => ['password', 'password_confirmation', 'token', 'api_key', 'secret',
             'authorization', 'two_factor_secret', 'two_factor_recovery_codes'],
```

في Sentry: `'send_default_pii' => false` — و`setUser(['id' => ...])` بالـ ID بس.

### الاحتفاظ والحذف

- سياسة احتفاظ مكتوبة لكل نوع بيانات
- سجل النشاط الأمني والمالي: بيتحفظ · الباقي: ١٢ شهر
- **حق الحذف:** أمر بيمسح بيانات مستخدم بالكامل عبر كل الجداول والملفات، وبيسيب سجل تدقيق مجهول الهوية
- **حق التصدير:** أمر بيصدّر كل بيانات مستخدم في ملف

---

## ١٤. مراجعة الأمان في كل PR

المراجِع بيفحص دول **قبل** أي حاجة تانية:

```markdown
- [ ] كل قدرة جديدة عبر Policy — مفيش `hasPermissionTo()` أو `can('x.y')`
- [ ] الحقول الحساسة عليها منع حفظ فعلي + اختبار
- [ ] الإجراءات الجماعية بـ `authorizeIndividualRecords()`
- [ ] الموديل الجديد عليه `BelongsToTenant` + اختبار عزل
- [ ] أي استعلام سجل واحد وراه `Gate::authorize()`
- [ ] `$fillable` صريح ومفيهوش حقول نظام
- [ ] مفيش `{!! !!}` على محتوى غير منقّى
- [ ] مفيش `whereRaw`/`orderByRaw` بمدخل مستخدم من غير قائمة بيضاء
- [ ] رفع ملفات بقائمة MIME بيضاء + حد حجم
- [ ] مفيش سر في الكود أو الاختبارات
- [ ] قنوات البث الجديدة خاصة ومقيّدة
- [ ] رسائل الأخطاء مش بتسرّب وجود سجلات
- [ ] `composer audit` أخضر
```

---

## ١٥. ما قبل الإطلاق — فحص أمني

- [ ] `APP_DEBUG=false` و `APP_ENV=production` — و`DebugModeCheck` في الـ health بيحرسهم
- [ ] Telescope مش مثبّت
- [ ] كل الرؤوس الأمنية موجودة — `securityheaders.com` بيدي **A** كحد أدنى
- [ ] TLS 1.2+ بس، والشهادة بتتجدد تلقائياً
- [ ] `TRUSTED_PROXIES` بقائمة CIDR صريحة لو التطبيق متاح مباشرة
- [ ] الكوكيز آمنة ومشفّرة
- [ ] Horizon و Pulse و عارض السجلات محميين بصلاحيات
- [ ] `/health` بمفتاح
- [ ] Rate limiting شغّال على الدخول و2FA والتصدير والرفع
- [ ] النسخ الاحتياطي مشفّر ومُختبَر استرجاعه فعلياً
- [ ] فحص أسرار في الـ CI
- [ ] **اختبار اختراق يدوي:** حساب `viewer` يحاول يوصل لكل مسار `admin` بالـ URL المباشر — كلها 403/404
- [ ] **اختبار عزل يدوي:** حساب من مستأجر أ يحاول يفتح `/admin/t/beta/...` وسجلات مستأجر ب بالـ ID المباشر

---

## ١٦. لو حصل حادث

مكتوب دلوقتي عشان محدش يفكّر وهو مذعور.

1. **احتوِ** — أوقف الوصول المتأثر (عطّل الحساب، اسحب التوكن، `php artisan down`)
2. **احفظ الأدلة** — انسخ اللوجات وسجل النشاط **قبل** أي تنظيف
3. **قدّر النطاق** — أي بيانات؟ أي مستأجرين؟ من إمتى؟ (هنا بيبان قد إيه `request_id` و`tenant_id` في اللوج مهمين)
4. **أصلح** — سُدّ الثغرة، انشر، تأكد
5. **دوّر الأسرار** — أي مفتاح ممكن يكون اتكشف
6. **بلّغ** — العملاء المتأثرين. الشفافية أرخص من الاكتشاف.
7. **راجع** — تقرير مكتوب: إيه حصل، ليه، إيه اللي منع اكتشافه بدري، إيه التغيير اللي يمنع تكراره. **بدون لوم أشخاص.**

**جهات الاتصال ووقت الاستجابة المستهدف يتكتبوا هنا قبل الإطلاق.**

---

## ١٧. معايير القبول

- [ ] كل بنود «مراجعة الأمان في كل PR» مطبّقة كاختبارات آلية حيثما أمكن
- [ ] `HtmlSanitizer` منفّذ ومطبّق على كل محتوى RichEditor (حفظاً وعرضاً)
- [ ] صفر `{!! !!}` على محتوى غير منقّى — اختبار آلي
- [ ] صفر `$guarded = []` — اختبار آلي
- [ ] صفر `whereRaw`/`orderByRaw` بمتغيّر مباشر — اختبار آلي
- [ ] كل قناة بث خاصة — اختبار
- [ ] رسائل الدخول موحّدة (مفيش تعداد حسابات) — اختبار
- [ ] فحص الأسرار في الـ CI شغّال
- [ ] `composer audit` جزء من `composer check`
- [ ] CSP في وضع التقرير على الأقل، مع خطة للفرض
- [ ] اختبار الاختراق اليدوي اتعمل ومُوثّق نتيجته
- [ ] خطة الاستجابة للحوادث فيها أسماء وأرقام فعلية
