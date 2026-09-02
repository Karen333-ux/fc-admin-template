<?php

declare(strict_types=1);

namespace Src\Contexts\Settings\Domain\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * إعدادات التخزين — **عامة**، مش قابلة للدهس من المستأجر.
 *
 * `docs/05` بند ٤: «الإعدادات اللي المستأجر يقدر يغيّرها = المظهر والإشعارات بس.
 * التخزين والبريد والأمان **عامة** (يديرها الـ super_admin)».
 *
 * يعني اختيار الديسك قرار على مستوى التطبيق — **مش** دلو لكل مستأجر. عزل
 * المستأجرين بيتعمل بالمسارات (`TenantAwarePathGenerator`) مش بديسكات منفصلة.
 */
final class StorageSettings extends Settings
{
    public string $default_disk;

    public string $private_disk;

    public string $conversions_disk;

    /** @var list<string> مجموعات محتواها خاص — روابط مؤقتة بس */
    public array $private_collections;

    /** @var array<string, string> تجاوز صريح لمجموعة معيّنة: ['avatar' => 's3'] */
    public array $collection_disks;

    public int $max_upload_size_kb;

    /** @var list<string> */
    public array $allowed_mimes;

    public static function group(): string
    {
        return 'storage';
    }

    /** @return array<int, string> */
    public static function encrypted(): array
    {
        return [];
    }
}
