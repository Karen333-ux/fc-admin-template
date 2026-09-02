<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\AuthorizationServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use Src\Contexts\Identity\IdentityServiceProvider;
use Src\Contexts\Settings\SettingsServiceProvider;

return [
    AppServiceProvider::class,
    AuthorizationServiceProvider::class,
    AdminPanelProvider::class,
    IdentityServiceProvider::class,
    SettingsServiceProvider::class,
];
