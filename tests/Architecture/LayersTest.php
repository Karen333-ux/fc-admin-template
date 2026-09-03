<?php

declare(strict_types=1);

use Src\Support\Infrastructure\Authorization\Policy;

/**
 * القاعدة الجديدة والأهم — هي اللي كانت مكسورة قبل ADR-011 ومحدش واخد باله.
 * (docs/23 بند ٣)
 */
arch('Support لا يستورد أي سياق')
    ->expect('Src\Support')
    ->not->toUse('Src\Contexts');

arch('طبقة Support\Domain نظيفة')      // ADR-006
    ->expect('Src\Support\Domain')
    ->not->toUse([
        'Filament\Facades',
        'Illuminate\Http',
        'Illuminate\Support\Facades',
    ]);

arch('طبقة Domain: واجهات أيوه، إطار لأ')   // ADR-012
    ->expect('Src\Contexts\Identity\Domain')
    ->not->toUse([
        'Filament\Facades',
        'Filament\Resources',
        'Filament\Forms',
        'Filament\Tables',
        'Illuminate\Http',
        'Illuminate\Support\Facades',
        'Livewire',
    ]);

arch('طبقة Domain في Settings: واجهات أيوه، إطار لأ')   // ADR-012
    ->expect('Src\Contexts\Settings\Domain')
    ->not->toUse([
        'Filament\Facades',
        'Filament\Resources',
        'Filament\Forms',
        'Filament\Tables',
        'Illuminate\Http',
        'Illuminate\Support\Facades',
        'Livewire',
    ]);

arch('طبقة Application في Settings لا تعرف الواجهة')
    ->expect('Src\Contexts\Settings\Application')
    ->not->toUse([
        'Filament',
        'Livewire',
        'Illuminate\Http',
    ]);

arch('طبقة Application لا تعرف الواجهة')
    ->expect('Src\Support\Application')
    ->not->toUse([
        'Filament',
        'Livewire',
        'Illuminate\Http',
    ]);

arch('كل Policy ترث الكلاس الأساسي')
    ->expect('Src\Contexts\Identity\Infrastructure\Policies')
    ->toExtend(Policy::class);

/**
 * سياق مايستوردش سياق تاني. (CLAUDE.md: «Contexts communicate via domain
 * events or a single public Application class — never by importing each
 * other's models»)
 *
 * ⚠️ القاعدة دي كانت **مش متغطّاة**: اختبار «Support لا يستورد أي سياق» فوق
 *    بيحمي اتجاه واحد بس. أول ما `docs/09` بند ٤ طلب إن `User` (Identity)
 *    يقرا `GeneralSettings` (Settings) مباشرةً، مكانش فيه حاجة بتمسكها —
 *    الحل كان عقد `LocaleDefaults` في `Support` (نمط ADR-022).
 *
 * الاتجاهين متغطّيين صراحةً: أي سياق جديد بيتضاف لازم يتضاف هنا.
 */
arch('Identity لا يستورد Settings')
    ->expect('Src\Contexts\Identity')
    ->not->toUse('Src\Contexts\Settings');

arch('Settings لا يستورد Identity')
    ->expect('Src\Contexts\Settings')
    ->not->toUse('Src\Contexts\Identity');
