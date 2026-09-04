<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Src\Contexts\Identity\Application\Actions\ForceLogoutAction;
use Src\Contexts\Identity\Application\Actions\TerminateDeviceAction;
use Src\Contexts\Identity\Domain\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * إنهاء الجلسات — «إنهاء الكل» و«إنهاء جهاز واحد». (docs/12 بند ٢)
 */
function seedSession(User $user, string $sessionId): void
{
    DB::table('sessions')->insert([
        'id' => $sessionId,
        'user_id' => $user->getKey(),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => base64_encode('test'),
        'last_activity' => now()->timestamp,
    ]);
}

it('ForceLogoutAction بيمسح كل الجلسات ما عدا واحدة', function (): void {
    $user = User::factory()->create();
    seedSession($user, 'keep-me');
    seedSession($user, 'kill-me-1');
    seedSession($user, 'kill-me-2');

    $count = app(ForceLogoutAction::class)->handle($user, 'keep-me');

    expect($count)->toBe(2)
        ->and(DB::table('sessions')->where('user_id', $user->getKey())->pluck('id')->all())
        ->toBe(['keep-me']);
});

it('ForceLogoutAction من غير استثناء بيمسح كل الجلسات', function (): void {
    $user = User::factory()->create();
    seedSession($user, 'a');
    seedSession($user, 'b');

    $count = app(ForceLogoutAction::class)->handle($user);

    expect($count)->toBe(2)
        ->and(DB::table('sessions')->where('user_id', $user->getKey())->count())->toBe(0);
});

it('ForceLogoutAction بيمسح صفوف الأجهزة المطابقة برضه', function (): void {
    $user = User::factory()->create();
    seedSession($user, 'keep-me');
    seedSession($user, 'kill-me');
    $user->devices()->create(['session_id' => 'keep-me', 'ip_address' => '127.0.0.1', 'last_active_at' => now()]);
    $user->devices()->create(['session_id' => 'kill-me', 'ip_address' => '127.0.0.1', 'last_active_at' => now()]);

    app(ForceLogoutAction::class)->handle($user, 'keep-me');

    expect($user->devices()->pluck('session_id')->all())->toBe(['keep-me']);
});

it('ForceLogoutAction بيمسح جهاز بـ session_id فاضي برضه', function (): void {
    // ⚠️ SQL `!=` مابتطابقش NULL أبداً — جهاز بـ session_id فاضي (مثلاً من
    // دخول من غير جلسة HTTP كاملة) لازم يتمسح زي أي جهاز تاني مش الجهاز
    // المستثنى، مش يفضل معلّق للأبد.
    $user = User::factory()->create();
    seedSession($user, 'keep-me');
    $user->devices()->create(['session_id' => 'keep-me', 'ip_address' => '127.0.0.1', 'last_active_at' => now()]);
    $user->devices()->create(['session_id' => null, 'ip_address' => '127.0.0.1', 'last_active_at' => now()]);

    app(ForceLogoutAction::class)->handle($user, 'keep-me');

    expect($user->devices()->pluck('session_id')->all())->toBe(['keep-me']);
});

it('ForceLogoutAction بيسجّل نشاط تحت security', function (): void {
    $user = User::factory()->create();
    seedSession($user, 'a');

    app(ForceLogoutAction::class)->handle($user);

    $activity = Activity::query()->where('event', 'force_logout')->latest()->first();

    expect($activity)->not->toBeNull()
        ->and($activity->log_name)->toBe('security')
        ->and($activity->description)->toBe(__('audit.events.user.force_logout'));
});

it('TerminateDeviceAction بيمسح جهاز واحد بس', function (): void {
    $user = User::factory()->create();
    seedSession($user, 'keep-me');
    seedSession($user, 'kill-me');
    $keep = $user->devices()->create(['session_id' => 'keep-me', 'ip_address' => '127.0.0.1', 'last_active_at' => now()]);
    $kill = $user->devices()->create(['session_id' => 'kill-me', 'ip_address' => '127.0.0.1', 'last_active_at' => now()]);

    app(TerminateDeviceAction::class)->handle($user, $kill);

    expect($user->devices()->pluck('session_id')->all())->toBe(['keep-me'])
        ->and(DB::table('sessions')->where('id', 'kill-me')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'keep-me')->exists())->toBeTrue();
});

it('TerminateDeviceAction بيرفض جهاز مستخدم تاني', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $device = $owner->devices()->create(['ip_address' => '127.0.0.1', 'last_active_at' => now()]);

    app(TerminateDeviceAction::class)->handle($stranger, $device);
})->throws(NotFoundHttpException::class);

it('TerminateDeviceAction بيسجّل نشاط تحت security', function (): void {
    $user = User::factory()->create();
    $device = $user->devices()->create(['ip_address' => '127.0.0.1', 'last_active_at' => now()]);

    app(TerminateDeviceAction::class)->handle($user, $device);

    $activity = Activity::query()->where('event', 'device_terminated')->latest()->first();

    expect($activity)->not->toBeNull()
        ->and($activity->log_name)->toBe('security');
});
