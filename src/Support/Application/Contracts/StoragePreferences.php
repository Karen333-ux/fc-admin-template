<?php

declare(strict_types=1);

namespace Src\Support\Application\Contracts;

/**
 * القيم اللي `DiskResolver` محتاجها عشان يقرر.
 *
 * ⚠️ **ليه العقد ده موجود أصلاً؟** `docs/04` بند ٣ بيكتب
 * `SettingsDrivenDiskResolver` وهو بياخد `StorageSettings` مباشرةً — وده
 * `Src\Support` بيستورد من `Src\Contexts`، مخالفة صريحة لـ ADR-011 والاختبار
 * المعماري بيمسكها.
 *
 * الحل عكس الاعتماد: `Support` بيعرّف العقد، وسياق `Settings` بينفّذه. الـ
 * resolver فضل مكانه ووظيفته زي ما `docs/04` عايز، من غير ما يعرف مين بيغذّيه.
 * (ADR-022)
 */
interface StoragePreferences
{
    public function defaultDisk(): string;

    public function privateDisk(): string;

    /** فاضي معناه: استخدم ديسك المجموعة نفسها */
    public function conversionsDisk(): string;

    /** @return list<string> */
    public function privateCollections(): array;

    /** @return array<string, string> */
    public function collectionDisks(): array;
}
