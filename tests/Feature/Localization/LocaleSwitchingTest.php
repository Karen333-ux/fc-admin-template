<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Settings\Domain\Settings\GeneralSettings;
use Src\Support\Application\Actions\SwitchLocaleAction;
use Src\Support\Domain\Exceptions\UnsupportedLocaleException;
use Src\Support\Presentation\Http\Middleware\SetLocale;

/** يشغّل الميدلوير على طلب فيه جلسة، ويرجّع اللغة اللي اتضبطت */
function localeAfterMiddleware(): string
{
    $request = Request::create('/admin');
    $request->setLaravelSession(Session::driver());

    app(SetLocale::class)->handle($request, fn (): Response => new Response);

    return app()->getLocale();
}

function switchLocale(): SwitchLocaleAction
{
    return app(SwitchLocaleAction::class);
}

// ────────────────────────────────────────────────────────────────
// التبديل — docs/10 بند ٤
// ────────────────────────────────────────────────────────────────

it('التبديل بيكتب في المستخدم وفي الجلسة', function (): void {
    // ⚠️ الاتنين مطلوبين: الطوابير مالهاش جلسة، فالإشعارات بتقرا من
    //    `users.locale`. الاعتماد على الجلسة لوحدها = بريد بلغة غلط.
    $user = User::factory()->create(['locale' => null]);
    Auth::login($user);

    switchLocale()->handle('en');

    expect($user->fresh()->locale)->toBe('en')
        ->and(Session::get('locale'))->toBe('en');
});

it('التبديل شغّال لضيف — بيكتب في الجلسة بس', function (): void {
    switchLocale()->handle('ar');

    expect(Session::get('locale'))->toBe('ar');
});

it('لغة مش مدعومة بترفض', function (): void {
    // ⚠️ القيمة جاية من الطلب. من غير الفحص ده أي نص بيتخزّن في DB
    //    وبيتحط في setLocale().
    switchLocale()->handle('fr');
})->throws(UnsupportedLocaleException::class);

it('اللغة المرفوضة مابتتكتبش في المستخدم ولا الجلسة', function (): void {
    $user = User::factory()->create(['locale' => 'ar']);
    Auth::login($user);

    try {
        switchLocale()->handle('de');
    } catch (UnsupportedLocaleException) {
        // متوقّع
    }

    expect($user->fresh()->locale)->toBe('ar')
        ->and(Session::get('locale'))->toBeNull();
});

// ────────────────────────────────────────────────────────────────
// الميدلوير — ترتيب الحسم
// ────────────────────────────────────────────────────────────────

it('لغة المستخدم بتغلب الجلسة', function (): void {
    $user = User::factory()->create(['locale' => 'en']);
    Auth::login($user);
    Session::put('locale', 'ar');

    expect(localeAfterMiddleware())->toBe('en');
});

it('الجلسة بتشتغل لما المستخدم مالوش لغة', function (): void {
    $user = User::factory()->create(['locale' => null]);
    Auth::login($user);
    Session::put('locale', 'en');

    expect(localeAfterMiddleware())->toBe('en');
});

it('الاحتياطي هو الإعداد العام لما مفيش مستخدم ولا جلسة', function (): void {
    $settings = app(GeneralSettings::class);
    $settings->default_locale = 'ar';
    $settings->save();

    expect(localeAfterMiddleware())->toBe('ar');
});

it('تغيير الإعداد العام بيغيّر لغة الضيف', function (): void {
    // بيثبت إن المصدر هو GeneralSettings مش قيمة مثبّتة
    $settings = app(GeneralSettings::class);
    $settings->default_locale = 'en';
    $settings->save();

    expect(localeAfterMiddleware())->toBe('en');
});

it('لغة مش مدعومة متخزّنة على المستخدم بتتجاهل', function (): void {
    // ⚠️ صف قديم بلغة اتشالت من الكونفيج مايصحّش يقفل اللوحة في وشه
    $settings = app(GeneralSettings::class);
    $settings->default_locale = 'ar';
    $settings->save();

    $user = User::factory()->create();
    $user->forceFill(['locale' => 'de'])->save();
    Auth::login($user);

    expect(localeAfterMiddleware())->toBe('ar');
});

// ────────────────────────────────────────────────────────────────
// الاتجاه بيتبع اللغة فعلاً
// ────────────────────────────────────────────────────────────────

it('الاتجاه بيتقلب بعد التبديل', function (): void {
    $user = User::factory()->create(['locale' => null]);
    Auth::login($user);

    switchLocale()->handle('ar');
    localeAfterMiddleware();
    expect(trans('filament-panels::layout.direction'))->toBe('rtl');

    switchLocale()->handle('en');
    localeAfterMiddleware();
    expect(trans('filament-panels::layout.direction'))->toBe('ltr');
});

it('رسائل التحقق بتطلع بلغة الطلب', function (): void {
    $user = User::factory()->create(['locale' => 'ar']);
    Auth::login($user);
    localeAfterMiddleware();

    expect(trans('validation.required'))->toBe(':attribute مطلوب.')
        ->and(trans('validation.required'))->not->toContain('field is required');
});

it('الميدلوير متسجّل على اللوحة', function (): void {
    $middleware = Filament::getPanel('admin')->getMiddleware();

    expect($middleware)->toContain(SetLocale::class);
});
