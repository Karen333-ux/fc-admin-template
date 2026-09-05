<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Providers\DynamicConfigServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;
use Src\Contexts\Identity\IdentityServiceProvider;
use Src\Contexts\Settings\SettingsServiceProvider;

return [
    AppServiceProvider::class,
    AuthorizationServiceProvider::class,
    // لازم قبل اللوحة: بيبني كونفيج البريد من الإعدادات المحفوظة
    DynamicConfigServiceProvider::class,
    AdminPanelProvider::class,
    // بوابة الدخول لـ Horizon — docs/13 بند ١
    HorizonServiceProvider::class,
    IdentityServiceProvider::class,
    SettingsServiceProvider::class,
];
