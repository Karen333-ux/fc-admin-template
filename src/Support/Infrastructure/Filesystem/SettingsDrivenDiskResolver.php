<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Filesystem;

use Src\Support\Application\Contracts\DiskResolver;
use Src\Support\Application\Contracts\StoragePreferences;
use Src\Support\Domain\Exceptions\InvalidDiskConfigurationException;

/**
 * تنفيذ `DiskResolver` مدفوع بالإعدادات. (docs/04 بند ٣)
 *
 * الترتيب مقصود: تجاوز صريح للمجموعة → مجموعة خاصة → الافتراضي.
 */
final readonly class SettingsDrivenDiskResolver implements DiskResolver
{
    public function __construct(private StoragePreferences $preferences) {}

    public function for(string $collection): string
    {
        // ١. تجاوز صريح لمجموعة معيّنة
        $override = $this->preferences->collectionDisks()[$collection] ?? null;

        if (is_string($override) && $override !== '') {
            return $this->validated($override);
        }

        // ٢. المجموعات الخاصة
        if ($this->isPrivate($collection)) {
            return $this->validated($this->preferences->privateDisk(), 'storage.private_disk');
        }

        // ٣. الافتراضي
        return $this->validated($this->preferences->defaultDisk(), 'storage.default_disk');
    }

    public function forConversions(string $collection): string
    {
        $disk = $this->preferences->conversionsDisk();

        // التحويلات دايماً على ديسك عام (المصغّرات مش سرية)، وفاضي معناه
        // «نفس ديسك المجموعة».
        return $disk === ''
            ? $this->for($collection)
            : $this->validated($disk, 'storage.conversions_disk');
    }

    public function isPrivate(string $collection): bool
    {
        return in_array($collection, $this->preferences->privateCollections(), true);
    }

    /**
     * ⚠️ **مفيش رجوع صامت لديسك تاني.**
     *
     * لو الإعداد بيقول `s3` والديسك ده مش معرّف، بنرمي. الرجوع لـ local هنا
     * معناه ملفات كان المفروض تروح للسحابة بتتكتب محلياً وبتضيع مع أول نشر.
     */
    private function validated(string $disk, ?string $setting = null): string
    {
        if ($disk === '') {
            throw InvalidDiskConfigurationException::empty($setting ?? 'storage.collection_disks');
        }

        if (! array_key_exists($disk, (array) config('filesystems.disks', []))) {
            throw InvalidDiskConfigurationException::notDefined($disk);
        }

        return $disk;
    }
}
