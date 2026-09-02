<?php

declare(strict_types=1);

namespace Src\Contexts\Settings\Infrastructure\Storage;

use Src\Contexts\Settings\Domain\Settings\StorageSettings;
use Src\Support\Application\Contracts\StoragePreferences;

/**
 * الجسر بين عقد `Support` وإعدادات سياق `Settings`. (ADR-022)
 *
 * الاتجاه هنا مهم: **السياق** بيعتمد على `Support`، مش العكس. عشان كده
 * الكلاس ده عايش في `Contexts\Settings` مش في `Support`.
 *
 * التخزين إعداد **عام** (docs/05 بند ٤)، فمفيش `TenantSettings` هنا — لو
 * قرأناها من إعدادات المستأجر، كل مستأجر كان هيقدر يوجّه ملفاته لديسك تاني.
 */
final readonly class SettingsStoragePreferences implements StoragePreferences
{
    public function __construct(private StorageSettings $settings) {}

    public function defaultDisk(): string
    {
        return $this->settings->default_disk;
    }

    public function privateDisk(): string
    {
        return $this->settings->private_disk;
    }

    public function conversionsDisk(): string
    {
        return $this->settings->conversions_disk;
    }

    /** @return list<string> */
    public function privateCollections(): array
    {
        // الفلترة على القيم الفاضية بس — الأنواع مضمونة من تعريف الإعداد
        return array_values(array_filter(
            $this->settings->private_collections,
            static fn (string $c): bool => $c !== '',
        ));
    }

    /** @return array<string, string> */
    public function collectionDisks(): array
    {
        return array_filter(
            $this->settings->collection_disks,
            static fn (string $d): bool => $d !== '',
        );
    }
}
