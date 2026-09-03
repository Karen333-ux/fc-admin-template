<?php

declare(strict_types=1);

namespace Src\Support\Application\Contracts;

/**
 * اللغة الافتراضية للتثبيت.
 *
 * ⚠️ العقد ده موجود عشان حدود السياقات. `docs/09` بند ٤ بيكتب على موديل
 * المستخدم:
 *
 *     return $this->locale ?? app(GeneralSettings::class)->default_locale;
 *
 * لكن `GeneralSettings` في سياق `Settings` و`User` في سياق `Identity`،
 * و`CLAUDE.md` بيمنع صراحةً إن سياق يستورد موديل سياق تاني. فالاتجاه
 * بيتعكس: العقد في `Support`، وسياق `Settings` بينفّذه، و`Identity`
 * بيعتمد على العقد بس. نفس نمط `StoragePreferences` (ADR-022).
 */
interface LocaleDefaults
{
    /** كود اللغة الافتراضية — زي `ar` أو `en` */
    public function default(): string;
}
