# ١٢ — المصادقة والأمان

> **الملف ده عن مصادقة المستخدم.** التفويض في `docs/19-policies.md` · أمان التطبيق في `docs/20-security.md`.

## ما يشمله

- المصادقة الثنائية (2FA) بـ TOTP
- إدارة الجلسات والأجهزة
- انتحال الشخصية للدعم الفني
- سياسة كلمات المرور
- الحماية من الهجمات الشائعة

---

## ١. المصادقة الثنائية (2FA)

### الجداول

```php
Schema::table('users', function (Blueprint $table) {
    $table->text('two_factor_secret')->nullable();          // مشفّر
    $table->text('two_factor_recovery_codes')->nullable();  // مشفّر
    $table->timestamp('two_factor_confirmed_at')->nullable();
});
```

### التنفيذ

Laravel Fortify بيوفّر منطق 2FA جاهز — بنستخدم الجزء ده منه بس من غير الـ views:

```bash
composer require laravel/fortify
```

```php
// config/fortify.php
'features' => [
    Features::twoFactorAuthentication([
        'confirm'        => true,
        'confirmPassword'=> true,
    ]),
],
'views' => false,      // ← إحنا بنبني الشاشات في Filament
```

### شاشة Filament

صفحة `TwoFactorSettings` بتعمل:
1. عرض QR code (`$user->twoFactorQrCodeSvg()`)
2. حقل تأكيد الكود
3. عرض أكواد الاسترجاع **مرة واحدة بس** مع زرار تنزيل
4. زرار «توليد أكواد جديدة»
5. زرار «تعطيل» (بيطلب كلمة المرور)

### فرض 2FA

```php
// config/security.php
'two_factor' => [
    'required_for_roles' => ['super_admin', 'admin'],
    'grace_period_days'  => 7,
],
```

ميدلوير بيمنع الدخول للوحة لو الدور بيتطلب 2FA وفترة السماح خلصت — بيوجّه لصفحة الإعداد.

---

## ٢. إدارة الجلسات والأجهزة

```php
Schema::create('user_devices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('session_id')->nullable()->index();
    $table->string('device_name')->nullable();       // "Chrome على Windows"
    $table->string('platform')->nullable();
    $table->string('browser')->nullable();
    $table->string('ip_address', 45);
    $table->string('country', 2)->nullable();
    $table->boolean('is_trusted')->default(false);
    $table->timestamp('last_active_at');
    $table->timestamps();

    $table->index(['user_id', 'last_active_at']);
});
```

مستمع على `Illuminate\Auth\Events\Login` بيسجّل/يحدّث الجهاز.

### شاشة «أجهزتي»

- قائمة الأجهزة النشطة مع آخر نشاط والموقع التقريبي
- الجهاز الحالي معلّم بوضوح
- زرار «إنهاء الجلسة» لكل جهاز
- زرار «إنهاء كل الجلسات الأخرى»
- إشعار بريد عند تسجيل دخول من جهاز جديد

```php
final class ForceLogoutAction
{
    public function handle(User $user, ?string $exceptSessionId = null): int
    {
        $query = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id);

        if ($exceptSessionId) {
            $query->where('id', '!=', $exceptSessionId);
        }

        $count = $query->delete();

        $user->devices()->where('session_id', '!=', $exceptSessionId)->delete();

        activity('security')
            ->performedOn($user)
            ->withProperties(['sessions_terminated' => $count])
            ->log('force_logout');

        return $count;
    }
}
```

> ⚠️ ده بيتطلب `SESSION_DRIVER=database` عشان نقدر نمسح جلسات محددة. لو Redis، استخدم مفاتيح بادئة بـ user id واعمل الحذف عبر `SCAN`. القرار: **قاعدة البيانات للجلسات، Redis للكاش والطوابير.**

---

## ٣. انتحال الشخصية (Impersonation)

```bash
composer require stechstudio/filament-impersonate:^5.6
```

```php
use STS\FilamentImpersonate\Actions\Impersonate;   // ملاحظة: Actions مباشرة، مش Tables\Actions

Impersonate::make()
    ->label(__('identity.actions.impersonate'))
    ->authorize('impersonate')          // ← UserPolicy::impersonate() — الصلاحية + القواعد
    ->authorizationTooltip()
    ->redirectTo(fn () => route('filament.admin.pages.dashboard'))
    ->requiresConfirmation();
```

### قواعد إلزامية

1. **صلاحية منفصلة** `impersonate.users` — مش جزء من `update.users`
2. **ممنوع انتحال `super_admin`** — قاعدة سلامة في `UserPolicy::impersonate()`، ومدرجة في `invariants()` عشان المدير العام نفسه مايتخطاهاش:

```php
public function impersonate(User $user, User $target): Response
{
    return $this->decide()
        ->permission($user, $this, 'impersonate')
        ->rule($user->isNot($target), 'self_target')
        ->rule(! $target->hasRole(config('authorization.super_admin_role')), 'cannot_impersonate_super_admin')
        ->rule(! app(ImpersonationContext::class)->isActive(), 'while_impersonating')
        ->response();
}

public function invariants(): array
{
    return ['impersonate', 'delete'];
}
```
3. **شريط تحذير واضح** في أعلى الشاشة طول فترة الانتحال:

```php
FilamentView::registerRenderHook(
    PanelsRenderHook::BODY_START,
    fn (): string => app(Impersonate::class)->isImpersonating()
        ? view('security.impersonation-banner')->render()
        : '',
);
```

4. **تسجيل الدخول والخروج** في `activity('security')`
5. **مدة قصوى** — الجلسة المنتحلة بتنتهي بعد ٣٠ دقيقة تلقائياً
6. **العمليات الحساسة معطّلة** أثناء الانتحال (تغيير كلمة مرور، حذف حساب، عمليات مالية)

```php
// في Policy أو Action
abort_if(app(ImpersonationContext::class)->isActive(), 403, __('security.blocked_while_impersonating'));
```

---

## ٤. سياسة كلمات المرور

```php
// AppServiceProvider::boot()
Password::defaults(function () {
    $rule = Password::min(config('security.password.min_length', 12))
        ->letters()
        ->mixedCase()
        ->numbers()
        ->symbols();

    return app()->isProduction()
        ? $rule->uncompromised()      // فحص ضد قواعد التسريبات
        : $rule;
});
```

إعدادات إضافية في `config/security.php`:
- انتهاء صلاحية كلمة المرور (اختياري، معطّل افتراضياً)
- منع إعادة استخدام آخر ٥ كلمات مرور
- إشعار بريد عند تغيير كلمة المرور (إجباري — في كتالوج الإشعارات)

---

## ٥. تحديد المعدّل (Rate Limiting)

```php
// AppServiceProvider::boot()
RateLimiter::for('login', fn (Request $r) => [
    Limit::perMinute(5)->by($r->input('email') . '|' . $r->ip()),
    Limit::perMinute(20)->by($r->ip()),
]);

RateLimiter::for('two-factor', fn (Request $r) => Limit::perMinute(5)->by($r->session()->get('login.id')));

RateLimiter::for('exports', fn (Request $r) => Limit::perHour(10)->by($r->user()->id));

RateLimiter::for('api', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));
```

بعد ٥ محاولات فاشلة: قفل مؤقت + إشعار للمستخدم + تسجيل في `activity('security')`.

---

## ٦. الرؤوس الأمنية

ميدلوير `SecurityHeaders`:

```php
$response->headers->add([
    'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
    'X-Content-Type-Options'    => 'nosniff',
    'X-Frame-Options'           => 'SAMEORIGIN',
    'Referrer-Policy'           => 'strict-origin-when-cross-origin',
    'Permissions-Policy'        => 'geolocation=(), microphone=(), camera=()',
]);
```

> HSTS في الإنتاج بس — لو حطيته محلياً على HTTP هيكسر التطوير.

الكوكيز:
```dotenv
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_HTTP_ONLY=true
SESSION_ENCRYPT=true
```

---

## ٧. مصائد أمنية شائعة (راجعها في كل PR)

| المصيدة | الحل |
|---|---|
| `->disabled()` لوحده على حقل حساس | لازم `->saved(false)` كمان |
| `shouldRegisterNavigation()` لوحده | لازم Policy على المورد |
| فحص صلاحية بنص (`can('x.y')`) | `can($ability, $model)` عبر Policy |
| `->authorize()` على إجراء جماعي | `->authorizeIndividualRecords()` |
| `->skipAuthorization()` | ممنوع نهائياً |
| `Gate::before` بيرجّع `true` للمدير دايماً | لازم يحترم `invariants()` |
| Mass assignment | `$fillable` صريح، مش `$guarded = []` |
| استعلام من غير tenant scope | الـ Global Scope بيرمي استثناء |
| رفع ملف من غير فحص MIME | `->acceptsMimeTypes()` + فحص السيرفر |
| رابط ملف خاص دائم | `getTemporaryUrl()` |
| قناة بث عامة | `Broadcast::channel` مع فحص |
| `env()` بره config | بيرجّع null مع `config:cache` |
| Telescope في الإنتاج | `--dev` فقط |
| `APP_DEBUG=true` في الإنتاج | `DebugModeCheck` في health |

---

## ٨. معايير القبول

- [ ] 2FA شغّال بالكامل: تفعيل، تأكيد، أكواد استرجاع، تعطيل
- [ ] الأدوار الإدارية مجبرة على 2FA بعد فترة السماح
- [ ] شاشة الأجهزة بتعرض الجلسات الحقيقية وبتنهيها فعلاً
- [ ] إشعار بريد عند دخول من جهاز جديد
- [ ] الانتحال بصلاحية منفصلة، بشريط تحذير، مسجّل، وبمدة قصوى
- [ ] ممنوع انتحال `super_admin` — اختبار Pest يثبت
- [ ] العمليات الحساسة معطّلة أثناء الانتحال
- [ ] `Password::defaults()` مطبّق مع `uncompromised()` في الإنتاج
- [ ] Rate limiting على الدخول و2FA والتصدير
- [ ] كل الرؤوس الأمنية موجودة — مفحوصة بـ securityheaders.com
- [ ] كل بنود جدول المصائد مفحوصة ومغطّاة باختبار
