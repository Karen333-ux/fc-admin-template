<?php

declare(strict_types=1);

namespace Src\Support\Presentation\Filament\Navigation;

use BackedEnum;
use Filament\Support\Contracts\Collapsible;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * مجموعات السايدبار — enum مش نصوص، عشان الترتيب والأيقونة والترجمة
 * يبقوا في مكان واحد. (docs/07 بند ٢)
 *
 * ⚠️ **الترتيب = ترتيب تعريف الحالات تحت، مش دالة `sort()`.**
 * `NavigationManager` بيرتّب بـ `array_search($case, $case::cases())`
 * (`vendor/filament/filament/src/Navigation/NavigationManager.php`)، يعني
 * دالة `sort()` اللي في `docs/07` بند ٢ **مابتتقريش خالص** لما العنصر
 * بيشاور على حالة الـ enum. عشان كده مافيش `sort()` هنا: قيمة ميتة كانت
 * هتوهم القارئ إنها بتتحكم في الترتيب.
 *
 * ⚠️ الترتيب ده **عقد**: السياقات الجاية بتترصّ في مكانها بين الحالات
 * الموجودة، مايعيدوش ترقيمها.
 *
 * ⚠️ مفيش بوابة على مستوى المجموعة. المجموعة بتظهر لو فيها عنصر ظاهر،
 * والعنصر بيتحكم فيه تفويضه هو (`canAccess()` / Policy). Filament بيشيل
 * المجموعات الفاضية لوحده — `->filter(fn ($group) => filled($group->getItems()))`
 * في نفس الملف فوق. يعني **التفويض على العنصر هو الحد الأمني**، والمجموعة
 * مجرد تجميع بصري.
 */
enum NavigationGroup implements Collapsible, HasIcon, HasLabel
{
    case Dashboard;
    case Identity;
    case Content;
    case Operations;
    case Reports;
    case Tenancy;
    case System;

    public function getLabel(): string
    {
        return __('navigation.groups.'.$this->key());
    }

    /** كل مجموعة ليها أيقونة — النوع أضيق من العقد بالقصد (مفيش null). */
    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Dashboard => Heroicon::OutlinedHome,
            self::Identity => Heroicon::OutlinedUsers,
            self::Content => Heroicon::OutlinedDocumentText,
            self::Operations => Heroicon::OutlinedCog6Tooth,
            self::Reports => Heroicon::OutlinedChartBar,
            self::Tenancy => Heroicon::OutlinedBuildingOffice2,
            self::System => Heroicon::OutlinedServerStack,
        };
    }

    /** كل المجموعات قابلة للطيّ، ومفيش واحدة بتبدأ مطويّة. (docs/07 بند ١) */
    public function isCollapsible(): bool
    {
        return true;
    }

    public function isCollapsed(): bool
    {
        return false;
    }

    /** مفتاح الترجمة — `Dashboard` → `dashboard` */
    public function key(): string
    {
        return mb_strtolower($this->name);
    }
}
