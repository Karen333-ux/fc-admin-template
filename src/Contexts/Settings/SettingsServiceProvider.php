<?php

declare(strict_types=1);

namespace Src\Contexts\Settings;

use Illuminate\Support\ServiceProvider;
use Src\Contexts\Settings\Application\TenantSettings;

final class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // مش singleton: الخدمة بتقرا المستأجر من TenantContext وقت النداء،
        // فمفيش حالة محفوظة جواها تتسرّب بين المستأجرين.
        $this->app->bind(TenantSettings::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/Lang', 'settings');
    }
}
