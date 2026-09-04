<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Infrastructure\Notifications\RepeatedFailedLoginAttemptsNotification;

/**
 * قفل مؤقت + إشعار بعد محاولات دخول فاشلة متكررة. (docs/12 بند ٥)
 *
 * ⚠️ `Auth::attempt()` مباشرة زي `FailedLoginActivityTest` — بيطلق حدث
 *    `Failed` الحقيقي من غير ما يعدّي على صفحة الدخول Livewire بتاعة
 *    Filament (اللي عندها قفل IP منفصل تماماً، مش موضوع الاختبار ده).
 */
function attemptFailedLogin(string $email): void
{
    Auth::attempt(['email' => $email, 'password' => 'wrong-password']);
}

it('أقل من الحد مفيش إشعار', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'under-threshold@example.test']);

    foreach (range(1, 4) as $i) {
        attemptFailedLogin('under-threshold@example.test');
    }

    Notification::assertNotSentTo($user, RepeatedFailedLoginAttemptsNotification::class);
});

it('الوصول للحد بالظبط بيبعت إشعار ويسجّل نشاط أمني', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'at-threshold@example.test']);

    foreach (range(1, 5) as $i) {
        attemptFailedLogin('at-threshold@example.test');
    }

    Notification::assertSentTo($user, RepeatedFailedLoginAttemptsNotification::class);

    $activity = $user->activitiesAsSubject()->where('event', 'login_lockout')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->log_name)->toBe('security');
});

it('محاولات بعد الحد مابتكررش الإشعار', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'beyond-threshold@example.test']);

    foreach (range(1, 7) as $i) {
        attemptFailedLogin('beyond-threshold@example.test');
    }

    Notification::assertSentToTimes($user, RepeatedFailedLoginAttemptsNotification::class, 1);

    expect($user->activitiesAsSubject()->where('event', 'login_lockout')->count())->toBe(1);
});

it('محاولات مستخدمين مختلفين مابتتخلطش', function (): void {
    Notification::fake();

    $userA = User::factory()->create(['email' => 'user-a@example.test']);
    $userB = User::factory()->create(['email' => 'user-b@example.test']);

    foreach (range(1, 5) as $i) {
        attemptFailedLogin('user-a@example.test');
    }

    foreach (range(1, 2) as $i) {
        attemptFailedLogin('user-b@example.test');
    }

    Notification::assertSentTo($userA, RepeatedFailedLoginAttemptsNotification::class);
    Notification::assertNotSentTo($userB, RepeatedFailedLoginAttemptsNotification::class);
});

it('إيميل مش موجود مايعملش استثناء ولا يبعت إشعار', function (): void {
    Notification::fake();

    foreach (range(1, 6) as $i) {
        attemptFailedLogin('ghost@example.test');
    }

    Notification::assertNothingSent();

    expect(Activity::query()->where('event', 'login_lockout')->count())->toBe(0);
});

it('القناة بريد إجبارية في الكتالوج', function (): void {
    expect(config('notifications.catalog.repeated_failed_login_attempts.required'))->toBe(['mail']);
});
