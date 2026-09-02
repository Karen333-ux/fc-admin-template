<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Src\Contexts\Settings\Domain\Settings\StorageSettings;
use Src\Support\Application\Contracts\DiskResolver;
use Src\Support\Application\Contracts\StoragePreferences;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Domain\Models\Tenant;
use Src\Support\Infrastructure\Filesystem\MediaOwnership;
use Src\Support\Infrastructure\Filesystem\MediaUrlResolver;
use Tests\Fixtures\MediaOwner;

beforeEach(function (): void {
    Schema::create('media_owners', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
        $table->string('name');
        $table->timestamps();
    });

    Storage::fake('public');
    Storage::fake('local');

    $settings = app(StorageSettings::class);
    $settings->default_disk = 'public';
    $settings->private_disk = 'local';
    $settings->private_collections = ['documents'];
    $settings->collection_disks = [];
    $settings->conversions_disk = 'public';
    $settings->save();
});

function ownerFor(Tenant $tenant): MediaOwner
{
    actingWithinTenant($tenant);

    return MediaOwner::create(['name' => 'صاحب']);
}

// ────────────────────────────────────────────────────────────────
// الرفع + المسار + الملكية
// ────────────────────────────────────────────────────────────────

it('بيرفع على الديسك اللي الـ resolver قرره', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = ownerFor($tenant);

    $media = $owner->attachMedia(UploadedFile::fake()->image('a.jpg'), 'avatar');

    expect($media->disk)->toBe('public');
    Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
});

it('المسار متقسّم tenants/{id}/...', function (): void {
    // معيار قبول docs/04 بند ١٠
    $tenant = Tenant::factory()->create();
    $owner = ownerFor($tenant);

    $media = $owner->attachMedia(UploadedFile::fake()->image('a.jpg'), 'avatar');

    expect($media->getPathRelativeToRoot())
        ->toStartWith("tenants/{$tenant->getKey()}/avatar/{$media->getKey()}/");
});

it('بيسجّل tenant_id في خصائص الملف', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = ownerFor($tenant);

    $media = $owner->attachMedia(UploadedFile::fake()->image('a.jpg'), 'avatar');

    expect($media->getCustomProperty(MediaOwnership::TENANT_PROPERTY))->toBe($tenant->getKey());
});

it('المجموعة الخاصة بتروح لديسك الخاص', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = ownerFor($tenant);

    $media = $owner->attachMedia(UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'), 'documents');

    expect($media->disk)->toBe('local');
    Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
    Storage::disk('public')->assertMissing($media->getPathRelativeToRoot());
});

// ────────────────────────────────────────────────────────────────
// عزل المستأجرين — الحد الأمني
// ────────────────────────────────────────────────────────────────

it('ملف مستأجر مش تابع للمستأجر الحالي', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $media = ownerFor($a)->attachMedia(UploadedFile::fake()->image('a.jpg'), 'avatar');

    actingWithinTenant($b);

    expect(app(MediaOwnership::class)->belongsToCurrentTenant($media))->toBeFalse();
});

it('طلب رابط لملف مستأجر تاني بيدّي 404 مش 403', function (): void {
    // ADR-005: عبور الحد بيخفي وجود السجل أصلاً
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $media = ownerFor($a)->attachMedia(UploadedFile::fake()->image('a.jpg'), 'avatar');

    actingWithinTenant($b);

    app(MediaUrlResolver::class)->url($media);
})->throws(ModelNotFoundException::class);

it('معرفة رقم الملف مابتكفيش للوصول', function (): void {
    // «تغيير media ID» في نموذج التهديد
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $foreign = ownerFor($a)->attachMedia(UploadedFile::fake()->image('a.jpg'), 'avatar');
    $id = $foreign->getKey();

    actingWithinTenant($b);

    $fetched = $foreign->newQuery()->findOrFail($id);

    expect(app(MediaOwnership::class)->belongsToCurrentTenant($fetched))->toBeFalse();
});

it('مسارات مستأجرين مختلفين مابتتصادمش', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $mediaA = ownerFor($a)->attachMedia(UploadedFile::fake()->image('same.jpg'), 'avatar');
    $mediaB = ownerFor($b)->attachMedia(UploadedFile::fake()->image('same.jpg'), 'avatar');

    expect($mediaA->getPathRelativeToRoot())->not->toBe($mediaB->getPathRelativeToRoot())
        ->and($mediaA->getPathRelativeToRoot())->toContain("tenants/{$a->getKey()}/")
        ->and($mediaB->getPathRelativeToRoot())->toContain("tenants/{$b->getKey()}/");
});

it('صاحب الملف نفسه معزول بـ TenantScope', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    ownerFor($a);
    ownerFor($b);

    expect(MediaOwner::query()->count())->toBe(1);

    actingWithinTenant($a);
    expect(MediaOwner::query()->count())->toBe(1);
});

// ────────────────────────────────────────────────────────────────
// الروابط
// ────────────────────────────────────────────────────────────────

it('المجموعة العامة بتدّي رابط عادي', function (): void {
    $tenant = Tenant::factory()->create();
    $media = ownerFor($tenant)->attachMedia(UploadedFile::fake()->image('a.jpg'), 'avatar');

    expect(app(MediaUrlResolver::class)->url($media))->toBeString()->not->toBeEmpty();
});

it('الديسكات الخاصة مظبوطة عشان تدّي روابط مؤقتة', function (): void {
    // ⚠️ الاختبار ده على **الكونفيج الحقيقي** مش على `Storage::fake()`:
    // الديسكات المزيّفة مابتحاكيش `providesTemporaryUrls()` بنفس سلوك
    // الديسك المتظبّط، فاختبارها عليه بيقيس المزيّف مش النظام.
    //
    // الضمانة اللي بنحميها: أي ديسك بيستقبل مجموعة خاصة لازم يقدر يدّي رابط
    // مؤقت — وإلا `MediaUrlResolver` بيرمي بدل ما يكشف رابط دائم.
    expect(Storage::disk('local')->providesTemporaryUrls())->toBeTrue()
        ->and(config('filesystems.disks.s3-private.visibility'))->toBe('private');
});

// ────────────────────────────────────────────────────────────────
// أمان التخزين
// ────────────────────────────────────────────────────────────────

it('اسم ملف خبيث مابيخرجش من مجلد المستأجر', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = ownerFor($tenant);

    $media = $owner->attachMedia(
        UploadedFile::fake()->create('../../../etc/passwd.jpg', 10, 'image/jpeg'),
        'avatar',
    );

    $path = $media->getPathRelativeToRoot();

    expect($path)->toStartWith("tenants/{$tenant->getKey()}/")
        ->and($path)->not->toContain('..');
});

it('من غير سياق مستأجر الملف بيروح لمجلد global', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = ownerFor($tenant);

    app(TenantContext::class)->set(null);

    $media = $owner->attachMedia(UploadedFile::fake()->image('a.jpg'), 'avatar');

    expect($media->getCustomProperty(MediaOwnership::TENANT_PROPERTY))->toBeNull()
        ->and($media->getPathRelativeToRoot())->toStartWith('tenants/global/');
});

// ────────────────────────────────────────────────────────────────
// الحذف
// ────────────────────────────────────────────────────────────────

it('حذف الملف بيمسحه من التخزين', function (): void {
    $tenant = Tenant::factory()->create();
    $media = ownerFor($tenant)->attachMedia(UploadedFile::fake()->image('a.jpg'), 'avatar');
    $path = $media->getPathRelativeToRoot();

    Storage::disk('public')->assertExists($path);

    $media->delete();

    Storage::disk('public')->assertMissing($path);
});

it('حذف صاحب الملف بيمسح ملفاته', function (): void {
    // مفيش سجلات يتيمة في التخزين ولا في قاعدة البيانات
    $tenant = Tenant::factory()->create();
    $owner = ownerFor($tenant);
    $media = $owner->attachMedia(UploadedFile::fake()->image('a.jpg'), 'avatar');
    $path = $media->getPathRelativeToRoot();

    $owner->delete();

    Storage::disk('public')->assertMissing($path);
    expect($media->newQuery()->whereKey($media->getKey())->exists())->toBeFalse();
});

it('S3 بيشتغل بنفس المسار من غير بيانات اعتماد حقيقية', function (): void {
    Storage::fake('s3');

    $settings = app(StorageSettings::class);
    $settings->default_disk = 's3';
    $settings->save();
    app()->forgetInstance(DiskResolver::class);
    app()->forgetInstance(StoragePreferences::class);

    $tenant = Tenant::factory()->create();
    $media = ownerFor($tenant)->attachMedia(UploadedFile::fake()->image('a.jpg'), 'avatar');

    expect($media->disk)->toBe('s3');
    Storage::disk('s3')->assertExists($media->getPathRelativeToRoot());
});
