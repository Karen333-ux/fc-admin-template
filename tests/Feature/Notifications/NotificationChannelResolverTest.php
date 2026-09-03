<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Exceptions;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Support\Application\Contracts\NotificationChannels;
use Src\Support\Domain\Exceptions\UnknownNotificationKeyException;
use Src\Support\Domain\Models\NotificationPreference;
use Src\Support\Domain\Models\Tenant;

function resolver(): NotificationChannels
{
    return app(NotificationChannels::class);
}

/** تفضيل محفوظ — tenant_id بره fillable فبيتحط صراحةً */
function savePreference(User $user, string $key, array $channels, bool $enabled = true, ?int $tenantId = null): NotificationPreference
{
    $preference = new NotificationPreference;
    $preference->fill([
        'user_id' => $user->getKey(),
        'notification_key' => $key,
        'channels' => $channels,
        'enabled' => $enabled,
    ]);
    $preference->tenant_id = $tenantId;
    $preference->save();

    return $preference;
}

// ────────────────────────────────────────────────────────────────
// الكتالوج
// ────────────────────────────────────────────────────────────────

it('بيرجّع القنوات الافتراضية لما مفيش تفضيل محفوظ', function (): void {
    $user = User::factory()->create();

    expect(resolver()->for($user, 'user_invited'))
        ->toBe(config('notifications.catalog.user_invited.default'));
});

it('مفتاح مش في الكتالوج بيرجع لقاعدة البيانات وبيتسجّل', function (): void {
    // ⚠️ الإشعار مايضيعش، بس الكتالوج الناقص لازم يوصل للمطوّر
    Exceptions::fake();

    $user = User::factory()->create();

    expect(resolver()->for($user, 'مفتاح_مش_موجود'))->toBe(['database']);

    Exceptions::assertReported(UnknownNotificationKeyException::class);
});

// ────────────────────────────────────────────────────────────────
// التفضيلات
// ────────────────────────────────────────────────────────────────

it('التفضيل المحفوظ بيغلب الافتراضي', function (): void {
    $user = User::factory()->create();
    savePreference($user, 'user_invited', ['database']);

    expect(resolver()->for($user, 'user_invited'))->toBe(['database']);
});

it('القناة اللي اتشالت من الكتالوج مابتفضلش شغالة', function (): void {
    // صف قديم فاكر قناة مابقتش متاحة — المتاح بيغلب المحفوظ
    $user = User::factory()->create();
    savePreference($user, 'user_invited', ['database', 'sms']);

    expect(resolver()->for($user, 'user_invited'))->not->toContain('sms');
});

// ────────────────────────────────────────────────────────────────
// القنوات الإجبارية — الحد الأمني
// ────────────────────────────────────────────────────────────────

it('القناة الإجبارية بتعدّي حتى لو المستخدم قفل الإشعار', function (): void {
    // ⚠️ الجوهر: `required` مش تفضيل. مستخدم قفل «اتدعيت» لازم يفضل
    //    يشوفها في اللوحة، وإلا مش هيعرف إنه اتدعى أصلاً.
    $user = User::factory()->create();
    savePreference($user, 'user_invited', ['mail'], enabled: false);

    $required = config('notifications.catalog.user_invited.required');

    expect(resolver()->for($user, 'user_invited'))->toBe($required)
        ->and($required)->not->toBeEmpty();
});

it('القناة الإجبارية بتتضاف حتى لو المستخدم مااختارهاش', function (): void {
    $user = User::factory()->create();
    savePreference($user, 'user_invited', ['mail']);

    expect(resolver()->for($user, 'user_invited'))->toContain('database');
});

// ────────────────────────────────────────────────────────────────
// عزل المستأجرين على التفضيلات — قرار المراجعة هـ
// ────────────────────────────────────────────────────────────────

it('تفضيل مستأجر مابيأثرش على مستأجر تاني', function (): void {
    // ⚠️ الجدول مافيهوش global scope (tenant_id قابل للفراغ)، فالتقييد
    //    صريح في الحلّال. الاختبار ده هو اللي بيمسك لو اتشال.
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $user = User::factory()->create();

    // في «أ» بس: قفل الإشعار
    savePreference($user, 'user_invited', ['mail'], enabled: false, tenantId: $a->getKey());

    actingWithinTenant($a);
    $inA = resolver()->for($user, 'user_invited');

    actingWithinTenant($b);
    $inB = resolver()->for($user, 'user_invited');

    expect($inA)->toBe(config('notifications.catalog.user_invited.required'))
        ->and($inB)->toBe(config('notifications.catalog.user_invited.default'))
        ->and($inA)->not->toBe($inB);
});

it('تفضيل المستأجر بيغلب التفضيل العام', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    savePreference($user, 'user_invited', ['database', 'mail'], tenantId: null);
    savePreference($user, 'user_invited', ['database'], tenantId: $tenant->getKey());

    actingWithinTenant($tenant);

    expect(resolver()->for($user, 'user_invited'))->toBe(['database']);
});

it('التفضيل العام بيشتغل من غير سياق مستأجر', function (): void {
    $user = User::factory()->create();
    savePreference($user, 'user_invited', ['database'], tenantId: null);

    expect(resolver()->for($user, 'user_invited'))->toBe(['database']);
});
