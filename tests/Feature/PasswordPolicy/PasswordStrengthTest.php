<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages\CreateUser;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages\EditUser;
use Src\Support\Domain\Models\Tenant;

/**
 * سياسة قوة كلمة المرور — `Password::defaults()`. (docs/12 بند ٤)
 */
function passwordContext(Tenant $tenant, object $user): void
{
    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);
    actingWithinTenant($tenant);
}

it('كلمة مرور قصيرة مرفوضة وقت الإنشاء', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    passwordContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(CreateUser::class)
        ->fillForm([
            'name' => 'مستخدم',
            'email' => 'weak@example.test',
            'password' => 'Ab1!',
        ])
        ->call('create')
        ->assertHasFormErrors(['password']);
});

it('كلمة مرور من غير حروف كبيرة مرفوضة', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    passwordContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(CreateUser::class)
        ->fillForm([
            'name' => 'مستخدم',
            'email' => 'weak2@example.test',
            'password' => 'lowercase-only-123!',
        ])
        ->call('create')
        ->assertHasFormErrors(['password']);
});

it('كلمة مرور من غير أرقام مرفوضة', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    passwordContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(CreateUser::class)
        ->fillForm([
            'name' => 'مستخدم',
            'email' => 'weak3@example.test',
            'password' => 'NoDigitsHere!',
        ])
        ->call('create')
        ->assertHasFormErrors(['password']);
});

it('كلمة مرور من غير رموز مرفوضة', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    passwordContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(CreateUser::class)
        ->fillForm([
            'name' => 'مستخدم',
            'email' => 'weak4@example.test',
            'password' => 'NoSymbolsHere123',
        ])
        ->call('create')
        ->assertHasFormErrors(['password']);
});

it('كلمة مرور مستوفية للسياسة مقبولة', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    passwordContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(CreateUser::class)
        ->fillForm([
            'name' => 'مستخدم',
            'email' => 'strong@example.test',
            'password' => 'Strong-Password9!',
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});

it('الحد الأدنى للطول قابل للضبط عبر الكونفيج', function (): void {
    config(['security.password.min_length' => 20]);

    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    passwordContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(CreateUser::class)
        ->fillForm([
            'name' => 'مستخدم',
            'email' => 'shortmin@example.test',
            // مستوفية كل الشروط ما عدا الطول (١٧ حرف) بعد ما رفعنا الحد لـ ٢٠
            'password' => 'Short-Pass9!',
        ])
        ->call('create')
        ->assertHasFormErrors(['password']);
});

it('نفس السياسة مفروضة وقت التعديل كمان', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    passwordContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $target->getRouteKey()])
        ->fillForm(['password' => 'weak'])
        ->call('save')
        ->assertHasFormErrors(['password']);
});

it('uncompromised() مش مفعّلة في بيئة الاختبار — من غير نداء شبكة', function (): void {
    // ⚠️ الاختبار ده بيوثّق القرار: uncompromised() بس في الإنتاج
    // (app()->isProduction())، عشان الاختبارات مايبقاش فيها نداء HTTP فعلي.
    expect(app()->isProduction())->toBeFalse()
        ->and(app()->environment())->toBe('testing');
});
