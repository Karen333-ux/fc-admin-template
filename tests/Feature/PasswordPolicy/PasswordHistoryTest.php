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
 * منع إعادة استخدام آخر N كلمة مرور. (docs/12 بند ٤)
 */
function historyContext(Tenant $tenant, object $user): void
{
    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);
    actingWithinTenant($tenant);
}

function changePassword(User $admin, User $target, string $password)
{
    return Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $target->getRouteKey()])
        ->fillForm(['password' => $password])
        ->call('save');
}

it('مينفعش تعيد استخدام كلمة المرور الحالية بالظبط', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create(['password' => 'Current-Password9!']);
    $target->tenants()->attach($tenant);

    historyContext($tenant, $admin);

    changePassword($admin, $target, 'Current-Password9!')
        ->assertHasFormErrors(['password']);
});

it('مينفعش تعيد استخدام كلمة مرور من آخر ٥', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    historyContext($tenant, $admin);

    changePassword($admin, $target, 'First-Password9!')->assertHasNoFormErrors();

    $target->refresh();

    changePassword($admin, $target, 'First-Password9!')->assertHasFormErrors(['password']);
});

it('تاريخ كلمات المرور بيتسجّل عند كل تغيير', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    historyContext($tenant, $admin);

    changePassword($admin, $target, 'First-Password9!')->assertHasNoFormErrors();

    expect($target->passwordHistories()->count())->toBe(1);
});

it('كلمة مرور أقدم من آخر ٥ بتبقى قابلة لإعادة الاستخدام', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create(['password' => 'Original-Password9!']);
    $target->tenants()->attach($tenant);

    historyContext($tenant, $admin);

    // ٦ تغييرات — «Original-Password9!» بتتسجّل في التاريخ عند أول تغيير،
    // وبعد ٦ تغييرات (يعني ٦ صفوف تاريخ) التقليم بيمسح أقدم واحد ويسيب ٥
    foreach (range(1, 6) as $i) {
        changePassword($admin, $target, "Password-Number-{$i}!")->assertHasNoFormErrors();
        $target->refresh();
    }

    expect($target->passwordHistories()->count())->toBe(5);

    // «Original-Password9!» كانت أول واحدة، دلوقتي برّه نافذة الـ٥ — تقدر تتكرر
    changePassword($admin, $target, 'Original-Password9!')->assertHasNoFormErrors();
});

it('التاريخ ما بيتأثرش بتغيير بيانات تانية غير كلمة المرور', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    historyContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $target->getRouteKey()])
        ->fillForm(['name' => 'اسم جديد', 'password' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($target->passwordHistories()->count())->toBe(0);
});

it('الإنشاء مايتأثرش بالتاريخ — مفيش سجل نتحقق منه أصلاً', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    historyContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(CreateUser::class)
        ->fillForm([
            'name' => 'مستخدم جديد',
            'email' => 'brand.new@example.test',
            'password' => 'Brand-New-Account9!',
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});
