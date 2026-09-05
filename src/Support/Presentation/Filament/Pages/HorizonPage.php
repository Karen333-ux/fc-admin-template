<?php

declare(strict_types=1);

namespace Src\Support\Presentation\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Src\Support\Presentation\Filament\Navigation\NavigationGroup;

/**
 * رابط الدخول لـ Horizon من جوّه اللوحة. (docs/13 بند ١)
 *
 * ⚠️ redirect مش embed: Horizon واجهته المستقلة بتوجيهها الخاص وأصولها
 *    الخاصة، ومحطوطة خارج نظام مصادقة Filament (`viewHorizon` Gate بتاعتها
 *    هي البوابة، مش سياق اللوحة). عمل iframe/embed كان هيحتاج تعامل مع
 *    CSP وترويسات إطارات منفصلة من غير أي فايدة حقيقية — الوثيقة بتسمح
 *    بالاتنين صراحةً («embed أو redirect»).
 *
 * ⚠️ الصلاحية هنا (`access.horizon`) بوابة تجربة استخدام بس — البوابة
 *    الأمنية الحقيقية هي Gate `viewHorizon` بتاعة Horizon نفسها
 *    (`HorizonServiceProvider::gate()`)، اللي بتتفحص تاني على مستوى
 *    راوتات Horizon نفسها بغض النظر عن الرابط ده.
 */
final class HorizonPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 91;

    public static function canAccess(): bool
    {
        return Gate::allows('access.horizon');
    }

    public static function getNavigationLabel(): string
    {
        return __('queues.horizon');
    }

    public function getTitle(): string
    {
        return __('queues.horizon');
    }

    public function mount(): void
    {
        $this->redirect('/'.ltrim((string) config('horizon.path', 'horizon'), '/'));
    }
}
