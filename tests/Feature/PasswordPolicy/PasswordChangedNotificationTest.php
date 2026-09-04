<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Infrastructure\Notifications\PasswordChangedNotification;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages\CreateUser;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages\EditUser;
use Src\Support\Domain\Models\Tenant;

/**
 * إشعار إجباري بالبريد عند تغيير كلمة المرور. (docs/12 بند ٤)
 */
function notifContext(Tenant $tenant, object $user): void
{
    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);
    actingWithinTenant($tenant);
}

it('تغيير كلمة المرور بيبعت إشعار للمستخدم صاحب الحساب', function (): void {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    notifContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $target->getRouteKey()])
        ->fillForm(['password' => 'Changed-Password9!'])
        ->call('save')
        ->assertHasNoFormErrors();

    Notification::assertSentTo($target, PasswordChangedNotification::class);
});

it('إنشاء حساب جديد مابيبعتش إشعار تغيير كلمة مرور', function (): void {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    notifContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(CreateUser::class)
        ->fillForm([
            'name' => 'مستخدم جديد',
            'email' => 'fresh.account@example.test',
            'password' => 'Fresh-Account9!',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // ⚠️ مش assertNothingSent(): Auth::login($admin) في notifContext() بيطلق
    // مستمع الجهاز الجديد بتاع Slice 4.5 — ده متوقّع ومش موضوع الاختبار ده.
    $created = User::query()->where('email', 'fresh.account@example.test')->firstOrFail();
    Notification::assertNotSentTo($created, PasswordChangedNotification::class);
});

it('تعديل بيانات تانية من غير كلمة مرور مابيبعتش إشعار', function (): void {
    Notification::fake();

    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);
    $target = User::factory()->create();
    $target->tenants()->attach($tenant);

    notifContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $target->getRouteKey()])
        ->fillForm(['name' => 'اسم جديد', 'password' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    // ⚠️ مش assertNothingSent() لنفس السبب فوق — Auth::login() بتطلق
    // إشعار الجهاز الجديد اللي مش موضوع الاختبار ده.
    Notification::assertNotSentTo($target, PasswordChangedNotification::class);
});

it('القناة بريد إجبارية في الكتالوج', function (): void {
    expect(config('notifications.catalog.password_changed.required'))->toBe(['mail']);
});
