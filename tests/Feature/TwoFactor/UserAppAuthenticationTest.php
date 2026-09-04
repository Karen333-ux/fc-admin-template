<?php

declare(strict_types=1);

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Support\Facades\DB;
use Src\Contexts\Identity\Domain\Models\User;

/**
 * موديل User يطبّق عقود Filament للمصادقة الثنائية. (docs/12 بند ١)
 */
it('السرّ فاضي افتراضياً ومحفوظ لما يتحدد', function (): void {
    $user = User::factory()->create();

    expect($user->getAppAuthenticationSecret())->toBeNull();

    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    expect($user->fresh()->getAppAuthenticationSecret())->toBe('JBSWY3DPEHPK3PXP');
});

it('السرّ متشفّر في قاعدة البيانات', function (): void {
    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    $raw = DB::table('users')->where('id', $user->id)->value('two_factor_secret');

    expect($raw)->not->toBe('JBSWY3DPEHPK3PXP')
        ->and($raw)->not->toBeNull();
});

it('اسم صاحب الحساب في تطبيق المصادقة هو البريد', function (): void {
    $user = User::factory()->create(['email' => 'holder@example.test']);

    expect($user->getAppAuthenticationHolderName())->toBe('holder@example.test');
});

it('أكواد الاسترجاع بتتحفظ وتترجع كمصفوفة', function (): void {
    $user = User::factory()->create();

    expect($user->getAppAuthenticationRecoveryCodes())->toBeNull();

    $user->saveAppAuthenticationRecoveryCodes(['code-one', 'code-two']);

    expect($user->fresh()->getAppAuthenticationRecoveryCodes())->toBe(['code-one', 'code-two']);
});

it('السرّ وأكواد الاسترجاع مخفيين من التسلسل', function (): void {
    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $user->saveAppAuthenticationRecoveryCodes(['code-one']);

    $array = $user->fresh()->toArray();

    expect($array)->not->toHaveKey('two_factor_secret')
        ->and($array)->not->toHaveKey('two_factor_recovery_codes');
});

it('تعطيل السرّ بيمسحه (null) — مطابق لسلوك DisableAppAuthenticationAction', function (): void {
    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $user->saveAppAuthenticationRecoveryCodes(['code-one']);

    $user->saveAppAuthenticationSecret(null);
    $user->saveAppAuthenticationRecoveryCodes(null);

    $fresh = $user->fresh();

    expect($fresh->getAppAuthenticationSecret())->toBeNull()
        ->and($fresh->getAppAuthenticationRecoveryCodes())->toBeNull();
});

it('isEnabled بتاعت Filament بتعتمد على وجود السرّ بس', function (): void {
    $user = User::factory()->create();
    $provider = app(AppAuthentication::class);

    expect($provider->isEnabled($user))->toBeFalse();

    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    expect($provider->isEnabled($user->fresh()))->toBeTrue();
});
