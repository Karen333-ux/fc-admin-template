<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('appearance.primary_color', '#12454F');
        $this->migrator->add('appearance.logo_light_path', null);
        $this->migrator->add('appearance.logo_dark_path', null);
        $this->migrator->add('appearance.favicon_path', null);
        $this->migrator->add('appearance.default_theme', 'system');
        $this->migrator->add('appearance.allow_theme_switch', true);
        $this->migrator->add('appearance.sidebar_default', 'expanded');
        $this->migrator->add('appearance.footer_text', [
            'ar' => '© ٢٠٢٦ كود المستقبل — جميع الحقوق محفوظة',
            'en' => '© 2026 Future Code — All rights reserved',
        ]);
        $this->migrator->add('appearance.font_family', 'IBM Plex Sans Arabic');
    }
};
