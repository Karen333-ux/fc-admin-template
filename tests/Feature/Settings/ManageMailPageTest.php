<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Src\Contexts\Settings\Domain\Settings\MailSettings;
use Src\Contexts\Settings\Presentation\Filament\Pages\ManageMail;
use Src\Support\Domain\Models\Tenant;

/** نفس ترتيب طلب حقيقي: تسجيل دخول ← لوحة ← مستأجر */
function mailContext(Tenant $tenant, object $user): void
{
    Auth::login($user);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);
    actingWithinTenant($tenant);
}

/** إعدادات SMTP شغّالة بكلمة مرور معروفة */
function seedSmtp(string $password = 'original-secret'): void
{
    $mail = app(MailSettings::class);
    $mail->driver = 'smtp';
    $mail->host = 'smtp.example.test';
    $mail->port = 587;
    $mail->username = 'postmaster';
    $mail->password = $password;
    $mail->encryption = 'tls';
    $mail->from_address = 'noreply@example.test';
    $mail->from_name = ['ar' => 'المرسل', 'en' => 'Sender'];
    $mail->save();
}

// ────────────────────────────────────────────────────────────────
// التفويض — الرفض الأول
// ────────────────────────────────────────────────────────────────

dataset('mail_matrix', [
    ['super_admin', true],
    ['admin', true],
    ['editor', false],
    ['viewer', false],
]);

it('مصفوفة أدوار manage_mail.settings', function (string $role, bool $allowed): void {
    $tenant = Tenant::factory()->create();
    $user = userWithRole($role, $tenant);

    actingWithinTenant($tenant);

    expect(Gate::forUser($user)->allows('manage_mail.settings'))
        ->toBe($allowed, "الدور {$role} على manage_mail.settings");
})->with('mail_matrix');

it('يمنع من لا يملك manage_mail من فتح الصفحة', function (string $role): void {
    $tenant = Tenant::factory()->create();
    $user = userWithRole($role, $tenant);

    mailContext($tenant, $user);

    expect(ManageMail::canAccess())->toBeFalse();
})->with(['editor', 'viewer']);

it('يمنع الوصول بالـ URL المباشر لمن لا يملك الصلاحية', function (): void {
    // إخفاء اللينك مابيقفلش الـ endpoint — الاختبار بيضرب الـ URL مباشرةً
    $tenant = Tenant::factory()->create();
    $viewer = userWithRole('viewer', $tenant);

    $this->actingAs($viewer)
        ->get(ManageMail::getUrl(tenant: $tenant))
        ->assertForbidden();
});

it('الرفض بيعدّي على Response برسالة مترجمة مش bool', function (): void {
    $tenant = Tenant::factory()->create();
    $viewer = userWithRole('viewer', $tenant);

    actingWithinTenant($tenant);

    $response = app(GateContract::class)
        ->forUser($viewer)
        ->inspect('manage_mail.settings');

    expect($response->allowed())->toBeFalse()
        ->and($response->message())->not->toBeEmpty()
        ->and($response->message())->not->toContain('authorization.');
});

it('admin بيفتح صفحة البريد', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    mailContext($tenant, $admin);

    expect(ManageMail::canAccess())->toBeTrue();

    Livewire::actingAs($admin)
        ->test(ManageMail::class)
        ->assertOk();
});

// ────────────────────────────────────────────────────────────────
// السر مابينزلش للمتصفح
// ────────────────────────────────────────────────────────────────

it('كلمة المرور المحفوظة مابتتبعتش للفورم', function (): void {
    // ⚠️ من غير mutateFormDataBeforeFill السر بينزل في حالة Livewire
    // وبيتشاف في الـ DOM كل مرة الصفحة تتفتح.
    seedSmtp('do-not-leak-me');

    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    mailContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(ManageMail::class)
        ->assertSet('data.password', '')
        ->assertDontSee('do-not-leak-me');
});

// ────────────────────────────────────────────────────────────────
// كلمة مرور فاضية = ماتتغيّرش
// ────────────────────────────────────────────────────────────────

it('حفظ بكلمة مرور فاضية بيحافظ على القديمة', function (): void {
    // ⚠️ الجوهر: الفورم بيتملى بكلمة مرور فاضية (فوق). يعني أي حفظ عادي
    // للصفحة كان هيمسح السر ويوقّف البريد من غير ما حد يقصد.
    seedSmtp('original-secret');

    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    mailContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(ManageMail::class)
        ->fillForm([
            'driver' => 'smtp',
            'host' => 'smtp.changed.test',
            'port' => 2525,
            'username' => 'postmaster',
            'password' => '',
            'encryption' => 'tls',
            'from_address' => 'noreply@example.test',
            'from_name' => ['ar' => 'المرسل', 'en' => 'Sender'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $mail = app(MailSettings::class)->refresh();

    expect($mail->host)->toBe('smtp.changed.test')
        ->and($mail->password)->toBe('original-secret');
});

it('كلمة مرور جديدة بتستبدل القديمة', function (): void {
    seedSmtp('original-secret');

    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    mailContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(ManageMail::class)
        ->fillForm([
            'driver' => 'smtp',
            'host' => 'smtp.example.test',
            'port' => 587,
            'username' => 'postmaster',
            'password' => 'rotated-secret',
            'encryption' => 'tls',
            'from_address' => 'noreply@example.test',
            'from_name' => ['ar' => 'المرسل', 'en' => 'Sender'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(MailSettings::class)->refresh()->password)->toBe('rotated-secret');
});

// ────────────────────────────────────────────────────────────────
// التحقق والترجمة
// ────────────────────────────────────────────────────────────────

it('بريد مُرسِل غير صالح بيترفض', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    mailContext($tenant, $admin);

    Livewire::actingAs($admin)
        ->test(ManageMail::class)
        ->fillForm(['from_address' => 'مش-بريد'])
        ->call('save')
        ->assertHasFormErrors(['from_address']);
});

it('عنوان الصفحة وتسميات البريد مترجمة في اللغتين', function (): void {
    foreach (['settings::settings.pages.mail', 'settings::settings.fields.mail_password'] as $key) {
        expect(trans($key, [], 'ar'))->not->toBe($key)
            ->and(trans($key, [], 'en'))->not->toBe($key)
            ->and(trans($key, [], 'ar'))->not->toBe(trans($key, [], 'en'));
    }
});
