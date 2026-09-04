<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Infrastructure\Notifications\NewDeviceLoginNotification;

/**
 * تسجيل الجهاز عند الدخول + إشعار الجهاز الجديد. (docs/12 بند ٢)
 *
 * ⚠️ بنضرب صفحة الدخول أولاً (من غير بيانات) عشان الجلسة تتبدأ فعلياً
 *    (StartSession middleware) والـ User-Agent يتسجّل على الـ request
 *    المربوط بالحاوية — بعدين Auth::attempt() على نفس الـ request ده
 *    عشان الحدث Login يتطلق حقيقي والمستمع يشوف نفس السياق.
 */
function loginAs(User $user, string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36'): void
{
    test()->withHeader('User-Agent', $userAgent)->get('/admin/login');

    Auth::attempt(['email' => $user->email, 'password' => 'password']);
}

it('أول دخول من جهاز بيسجّل صف جهاز جديد', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    loginAs($user);

    expect($user->devices()->count())->toBe(1);

    $device = $user->devices()->first();

    expect($device->browser)->toBe('Chrome')
        ->and($device->platform)->toBe('Windows')
        ->and($device->last_active_at)->not->toBeNull();
});

it('أول دخول من جهاز بيبعت إشعار جهاز جديد', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    loginAs($user);

    Notification::assertSentTo($user, NewDeviceLoginNotification::class);
});

it('دخول تاني من نفس الجهاز بيحدّث الصف الموجود مش بيعمل واحد جديد', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    loginAs($user);
    auth()->logout();
    loginAs($user);

    expect($user->devices()->count())->toBe(1);
});

it('دخول تاني من نفس الجهاز مابيبعتش إشعار جهاز جديد تاني', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    loginAs($user);
    auth()->logout();
    loginAs($user);

    Notification::assertSentToTimes($user, NewDeviceLoginNotification::class, 1);
});

it('دخول من متصفح مختلف بيتسجّل كجهاز تاني منفصل', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    loginAs($user, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36');
    auth()->logout();
    loginAs($user, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:120.0) Gecko/20100101 Firefox/120.0');

    expect($user->devices()->count())->toBe(2);
});
