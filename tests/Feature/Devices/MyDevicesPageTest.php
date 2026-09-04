<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Presentation\Filament\Pages\MyDevices;
use Src\Support\Domain\Models\Tenant;

/** نفس ترتيب طلب حقيقي: تسجيل دخول ← لوحة ← مستأجر */
function devicesContext(Tenant $tenant, object $user): void
{
    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);
    actingWithinTenant($tenant);
}

it('أي دور مصادَق عليه يقدر يفتح صفحة أجهزتي — ذاتية الخدمة', function (): void {
    $tenant = Tenant::factory()->create();
    $editor = userWithRole('editor', $tenant);

    devicesContext($tenant, $editor);

    expect(MyDevices::canAccess())->toBeTrue();

    Livewire::actingAs($editor)->test(MyDevices::class)->assertOk();
});

it('الجدول بيعرض أجهزة المستخدم الحالي بس', function (): void {
    $tenant = Tenant::factory()->create();
    $me = userWithRole('editor', $tenant);
    $stranger = User::factory()->create();

    $myDevice = $me->devices()->create(['ip_address' => '1.1.1.1', 'last_active_at' => now()]);
    $strangerDevice = $stranger->devices()->create(['ip_address' => '2.2.2.2', 'last_active_at' => now()]);

    devicesContext($tenant, $me);

    Livewire::actingAs($me)
        ->test(MyDevices::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$myDevice])
        ->assertCanNotSeeTableRecords([$strangerDevice]);
});

it('زرار إنهاء بيمسح الجهاز والجلسة', function (): void {
    $tenant = Tenant::factory()->create();
    $me = userWithRole('editor', $tenant);
    DB::table('sessions')->insert([
        'id' => 'other-device-session',
        'user_id' => $me->getKey(),
        'ip_address' => '1.1.1.1',
        'user_agent' => 'test',
        'payload' => base64_encode('x'),
        'last_activity' => now()->timestamp,
    ]);
    $device = $me->devices()->create([
        'session_id' => 'other-device-session',
        'ip_address' => '1.1.1.1',
        'last_active_at' => now(),
    ]);

    devicesContext($tenant, $me);

    Livewire::actingAs($me)
        ->test(MyDevices::class)
        ->callTableAction('terminate', $device);

    expect($me->devices()->whereKey($device->getKey())->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'other-device-session')->exists())->toBeFalse();
});

it('زرار إنهاء الجهاز الحالي مخفي', function (): void {
    $tenant = Tenant::factory()->create();
    $me = userWithRole('editor', $tenant);

    devicesContext($tenant, $me);

    $currentSessionId = session()->getId();
    $currentDevice = $me->devices()->create([
        'session_id' => $currentSessionId,
        'ip_address' => '1.1.1.1',
        'last_active_at' => now(),
    ]);

    Livewire::actingAs($me)
        ->test(MyDevices::class)
        ->assertTableActionHidden('terminate', $currentDevice);
});

it('زرار إنهاء كل الجلسات الأخرى بيسيب الجهاز الحالي بس', function (): void {
    $tenant = Tenant::factory()->create();
    $me = userWithRole('editor', $tenant);

    devicesContext($tenant, $me);
    $currentSessionId = session()->getId();

    DB::table('sessions')->insert([
        ['id' => $currentSessionId, 'user_id' => $me->getKey(), 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => now()->timestamp],
        ['id' => 'other-1', 'user_id' => $me->getKey(), 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => now()->timestamp],
    ]);
    $me->devices()->create(['session_id' => $currentSessionId, 'ip_address' => '1.1.1.1', 'last_active_at' => now()]);
    $me->devices()->create(['session_id' => 'other-1', 'ip_address' => '1.1.1.1', 'last_active_at' => now()]);

    Livewire::actingAs($me)
        ->test(MyDevices::class)
        ->callTableAction('terminateOthers');

    expect($me->devices()->pluck('session_id')->all())->toBe([$currentSessionId])
        ->and(DB::table('sessions')->where('id', $currentSessionId)->exists())->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'other-1')->exists())->toBeFalse();
});
