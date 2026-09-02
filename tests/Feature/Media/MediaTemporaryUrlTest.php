<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Src\Contexts\Settings\Domain\Settings\StorageSettings;
use Src\Support\Application\Contracts\DiskResolver;
use Src\Support\Application\Contracts\StoragePreferences;
use Src\Support\Domain\Models\Tenant;
use Src\Support\Infrastructure\Filesystem\MediaUrlResolver;
use Tests\Fixtures\MediaOwner;

/**
 * ⚠️ الملف ده **مابيستخدمش `Storage::fake()`** بالقصد.
 *
 * `Storage::fake()` بتركّب `buildTemporaryUrlsUsing()` بتاعتها اللي بترجّع
 * `URL::to($path.'?expiration='.$ts)` — ده رابط مزيّف مالوش أي علاقة بآلية
 * الديسك الحقيقي. اختبار الانتهاء عليه بيقيس المزيّف، مش النظام.
 *
 * الديسك الحقيقي `local` عنده `serve => true`، فـ Laravel بيولّد
 * `temporarySignedRoute('storage.local', ...)` — يعني `expires` + `signature`
 * حقيقيين. ده اللي بنثبته هنا. (معيار قبول docs/04 بند ١٠: «رابطه مؤقت وبينتهي»)
 */
beforeEach(function (): void {
    Schema::create('media_owners', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
        $table->string('name');
        $table->timestamps();
    });

    $settings = app(StorageSettings::class);
    $settings->default_disk = 'public';
    $settings->private_disk = 'local';
    $settings->private_collections = ['documents'];
    $settings->collection_disks = [];
    $settings->conversions_disk = 'public';
    $settings->save();

    app()->forgetInstance(DiskResolver::class);
    app()->forgetInstance(StoragePreferences::class);
});

afterEach(function (): void {
    // ملفات حقيقية اتكتبت على القرص — مابنسيبهاش ورانا
    File::deleteDirectory(storage_path('app/private/tenants'));
    File::deleteDirectory(storage_path('app/public/tenants'));
});

function privateMediaOwner(Tenant $tenant): MediaOwner
{
    actingWithinTenant($tenant);

    return MediaOwner::create(['name' => 'صاحب']);
}

/** @return array<string, string> باراميترات الاستعلام في الرابط */
function urlQuery(string $url): array
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    /** @var array<string, string> $query */
    return $query;
}

it('رابط المجموعة الخاصة موقّع وبينتهي فعلاً — على الديسك الحقيقي', function (): void {
    $tenant = Tenant::factory()->create();
    $media = privateMediaOwner($tenant)
        ->attachMedia(UploadedFile::fake()->create('عقد.pdf', 10, 'application/pdf'), 'documents');

    expect($media->disk)->toBe('local');

    $url = app(MediaUrlResolver::class)->url($media);
    $query = urlQuery($url);

    // آلية الانتهاء الحقيقية للديسك المتظبّط: توقيع مؤقّت على route التخزين
    expect($query)->toHaveKeys(['expires', 'signature'])
        ->and($query['signature'])->not->toBeEmpty()
        ->and($url)->toContain("tenants/{$tenant->getKey()}/documents/{$media->getKey()}/");

    // الرابط صالح دلوقتي وبينتهي بعد المدة المتظبّطة (٥ دقايق)
    $expires = (int) $query['expires'];

    expect($expires)->toBeGreaterThan(now()->timestamp)
        ->and($expires)->toBeGreaterThanOrEqual(now()->addMinutes(4)->timestamp)
        ->and($expires)->toBeLessThanOrEqual(now()->addMinutes(6)->timestamp);
});

it('إعداد صفر مابيولّدش رابط منتهي — الحد الأدنى دقيقة', function (): void {
    // من غير max(1, ...) الإعداد ده بيدّي now()->addMinutes(0) — رابط ميّت
    // من لحظة توليده.
    config(['media-library.temporary_url_default_lifetime' => 0]);

    $tenant = Tenant::factory()->create();
    $media = privateMediaOwner($tenant)
        ->attachMedia(UploadedFile::fake()->create('عقد.pdf', 10, 'application/pdf'), 'documents');

    $expires = (int) urlQuery(app(MediaUrlResolver::class)->url($media))['expires'];

    expect($expires)->toBeGreaterThan(now()->timestamp)
        ->and($expires)->toBeGreaterThanOrEqual(now()->addSeconds(30)->timestamp);
});

it('مجموعة خاصة على ديسك مابيدعمش الروابط المؤقتة بترمي — مش بترجّع رابط دائم', function (): void {
    // الفشل الآمن على **ديسك حقيقي**: public ديسك محلي من غير serve،
    // فـ providesTemporaryUrls() بترجّع false. البديل الصامت هنا كان
    // هيبقى رابط دائم لملف خاص.
    $settings = app(StorageSettings::class);
    $settings->collection_disks = ['documents' => 'public'];
    $settings->save();
    app()->forgetInstance(DiskResolver::class);
    app()->forgetInstance(StoragePreferences::class);

    $tenant = Tenant::factory()->create();
    $media = privateMediaOwner($tenant)
        ->attachMedia(UploadedFile::fake()->create('عقد.pdf', 10, 'application/pdf'), 'documents');

    expect($media->disk)->toBe('public');

    app(MediaUrlResolver::class)->url($media);
})->throws(RuntimeException::class);
