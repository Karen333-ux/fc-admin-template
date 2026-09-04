<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Presentation\Filament\Pages\EditProfile;
use Src\Support\Domain\Models\Tenant;

/**
 * منع إعادة الاستخدام مفروض على التغيير الذاتي كمان — `EditProfile`
 * المخصصة، مش بس `UserResource` الإداري. (docs/12 بند ٤)
 */
function selfServiceContext(Tenant $tenant, User $user): void
{
    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);
    actingWithinTenant($tenant);
}

it('صفحة البروفايل هي نسختنا المخصصة', function (): void {
    expect(Filament::getCurrentOrDefaultPanel()->getProfilePage())->toBe(EditProfile::class);
});

it('مينفعش تعيد استخدام كلمة المرور الحالية من صفحة البروفايل الذاتية', function (): void {
    $tenant = Tenant::factory()->create();
    $user = userWithRole('editor', $tenant);
    $user->forceFill(['password' => 'Current-Self-Password9!'])->save();

    selfServiceContext($tenant, $user);

    Livewire::actingAs($user)
        ->test(EditProfile::class)
        ->fillForm([
            'password' => 'Current-Self-Password9!',
            'passwordConfirmation' => 'Current-Self-Password9!',
            'currentPassword' => 'Current-Self-Password9!',
        ])
        ->call('save')
        ->assertHasFormErrors(['password']);
});

it('كلمة مرور ذاتية جديدة ومختلفة مقبولة', function (): void {
    $tenant = Tenant::factory()->create();
    $user = userWithRole('editor', $tenant);
    $user->forceFill(['password' => 'Current-Self-Password9!'])->save();

    selfServiceContext($tenant, $user);

    Livewire::actingAs($user)
        ->test(EditProfile::class)
        ->fillForm([
            'password' => 'Brand-New-Self-Password9!',
            'passwordConfirmation' => 'Brand-New-Self-Password9!',
            'currentPassword' => 'Current-Self-Password9!',
        ])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('سياسة القوة مفروضة على صفحة البروفايل الذاتية برضه', function (): void {
    $tenant = Tenant::factory()->create();
    $user = userWithRole('editor', $tenant);
    $user->forceFill(['password' => 'Current-Self-Password9!'])->save();

    selfServiceContext($tenant, $user);

    Livewire::actingAs($user)
        ->test(EditProfile::class)
        ->fillForm([
            'password' => 'weak',
            'passwordConfirmation' => 'weak',
            'currentPassword' => 'Current-Self-Password9!',
        ])
        ->call('save')
        ->assertHasFormErrors(['password']);
});
