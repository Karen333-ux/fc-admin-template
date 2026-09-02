<?php

declare(strict_types=1);

use Src\Contexts\Settings\Domain\Settings\StorageSettings;
use Src\Support\Application\Contracts\DiskResolver;
use Src\Support\Application\Contracts\StoragePreferences;
use Src\Support\Domain\Exceptions\InvalidDiskConfigurationException;

/** يظبط إعدادات التخزين العامة ويعيد بناء الـ resolver */
function storageSettings(array $overrides = []): void
{
    $settings = app(StorageSettings::class);

    foreach ($overrides as $key => $value) {
        $settings->{$key} = $value;
    }

    $settings->save();

    // الـ resolver singleton — نفضّيه عشان ياخد الإعدادات الجديدة
    app()->forgetInstance(DiskResolver::class);
    app()->forgetInstance(StoragePreferences::class);
}

it('بيرجّع الديسك الافتراضي لمجموعة عادية', function (): void {
    storageSettings(['default_disk' => 'public']);

    expect(app(DiskResolver::class)->for('avatar'))->toBe('public');
});

it('بيرجّع ديسك الخاص للمجموعات الخاصة', function (): void {
    storageSettings([
        'default_disk' => 'public',
        'private_disk' => 'local',
        'private_collections' => ['documents'],
    ]);

    $disks = app(DiskResolver::class);

    expect($disks->isPrivate('documents'))->toBeTrue()
        ->and($disks->for('documents'))->toBe('local')
        ->and($disks->isPrivate('avatar'))->toBeFalse();
});

it('التجاوز الصريح للمجموعة بيغلب الافتراضي والخاص', function (): void {
    storageSettings([
        'default_disk' => 'public',
        'private_disk' => 'local',
        'private_collections' => ['documents'],
        'collection_disks' => ['documents' => 's3', 'avatar' => 's3'],
    ]);

    $disks = app(DiskResolver::class);

    expect($disks->for('avatar'))->toBe('s3')
        ->and($disks->for('documents'))->toBe('s3');
});

it('ديسك التحويلات مستقل، وفاضي معناه نفس ديسك المجموعة', function (): void {
    storageSettings(['default_disk' => 'local', 'conversions_disk' => 'public']);
    expect(app(DiskResolver::class)->forConversions('avatar'))->toBe('public');

    storageSettings(['conversions_disk' => '']);
    expect(app(DiskResolver::class)->forConversions('avatar'))->toBe('local');
});

// ────────────────────────────────────────────────────────────────
// الفشل الآمن — مفيش رجوع صامت
// ────────────────────────────────────────────────────────────────

it('بيرمي على ديسك مش معرّف بدل ما يرجع لديسك تاني', function (): void {
    // ⚠️ الجوهر: لو الإعداد بيقول s3 وهو مش معرّف، الرجوع الصامت لـ local
    // معناه ملفات بتتكتب محلياً وبتضيع مع أول نشر.
    storageSettings(['default_disk' => 'ديسك-مش-موجود']);

    app(DiskResolver::class)->for('avatar');
})->throws(InvalidDiskConfigurationException::class);

it('بيرمي على ديسك خاص مش معرّف', function (): void {
    storageSettings([
        'private_disk' => 'not-a-disk',
        'private_collections' => ['documents'],
    ]);

    app(DiskResolver::class)->for('documents');
})->throws(InvalidDiskConfigurationException::class);

it('بيرمي على إعداد ديسك فاضي', function (): void {
    storageSettings(['default_disk' => '']);

    app(DiskResolver::class)->for('avatar');
})->throws(InvalidDiskConfigurationException::class);

it('بيرمي على تجاوز مجموعة لديسك مش معرّف', function (): void {
    storageSettings(['collection_disks' => ['avatar' => 'nope']]);

    app(DiskResolver::class)->for('avatar');
})->throws(InvalidDiskConfigurationException::class);

// ────────────────────────────────────────────────────────────────
// S3 — من الكونفيج، من غير أي بيانات اعتماد في الكود
// ────────────────────────────────────────────────────────────────

it('ديسكات S3 معرّفة وبتاخد قيمها من البيئة', function (): void {
    $disks = config('filesystems.disks');

    expect($disks)->toHaveKeys(['s3', 's3-private'])
        ->and($disks['s3']['driver'])->toBe('s3')
        ->and($disks['s3-private']['driver'])->toBe('s3')
        // الخاص private والعام public — الفرق ده هو اللي بيمنع رابط دائم
        ->and($disks['s3-private']['visibility'])->toBe('private')
        ->and($disks['s3']['visibility'])->toBe('public')
        // الفشل بيرمي، مابيعديش بصمت
        ->and($disks['s3']['throw'])->toBeTrue()
        ->and($disks['s3-private']['throw'])->toBeTrue();
});

it('مفيش بيانات اعتماد AWS مكتوبة في الكونفيج', function (): void {
    $raw = file_get_contents(config_path('filesystems.php'));

    // كل قيمة حساسة لازم تعدّي على env()
    expect($raw)->toContain("env('AWS_ACCESS_KEY_ID')")
        ->and($raw)->toContain("env('AWS_SECRET_ACCESS_KEY')")
        ->and($raw)->not->toMatch('/AKIA[0-9A-Z]{16}/');
});

it('S3 بيتحل زي أي ديسك تاني من غير كود خاص', function (): void {
    storageSettings(['default_disk' => 's3']);

    expect(app(DiskResolver::class)->for('avatar'))->toBe('s3');
});
