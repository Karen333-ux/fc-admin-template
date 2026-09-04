<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;
use Src\Contexts\Identity\Domain\Models\User;

/**
 * محاولات الدخول الفاشلة — كشف هجمات التخمين، «لازم تتسجّل دايماً». (docs/11 بند ٥)
 */
it('محاولة دخول بكلمة مرور غلط بتسجّل نشاط login_failed', function (): void {
    $user = User::factory()->create(['email' => 'known@example.test']);

    Auth::attempt(['email' => 'known@example.test', 'password' => 'wrong-password']);

    $activity = $user->activitiesAsSubject()->where('event', 'login_failed')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->log_name)->toBe('security')
        ->and($activity->properties['email'])->toBe('known@example.test');
});

it('محاولات فاشلة متكررة كل واحدة بتتسجّل لوحدها', function (): void {
    $user = User::factory()->create(['email' => 'repeat@example.test']);

    for ($i = 0; $i < 3; $i++) {
        Auth::attempt(['email' => 'repeat@example.test', 'password' => 'wrong']);
    }

    expect($user->activitiesAsSubject()->where('event', 'login_failed')->count())->toBe(3);
});

it('محاولة بإيميل مش موجود بتتسجّل من غير subject', function (): void {
    event(new Failed('web', null, ['email' => 'ghost@example.test', 'password' => 'x']));

    $activity = Activity::query()
        ->where('event', 'login_failed')
        ->whereNull('subject_id')
        ->latest()
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['email'])->toBe('ghost@example.test');
});

it('كلمة المرور المُدخَلة مابتوصلش لسجل النشاط', function (): void {
    $user = User::factory()->create(['email' => 'secret@example.test']);

    Auth::attempt(['email' => 'secret@example.test', 'password' => 'super-secret-guess']);

    $activity = $user->activitiesAsSubject()->where('event', 'login_failed')->first();

    expect(json_encode($activity->properties->toArray()))->not->toContain('super-secret-guess');
});
