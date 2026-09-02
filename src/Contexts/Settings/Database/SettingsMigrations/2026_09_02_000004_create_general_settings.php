<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // الاسم والوصف مترجمين — الشكل ده هو نفسه بتاع appearance.footer_text
        // عشان طبقة العرض تتعامل معاهم بنفس قاعدة الرجوع للغة الاحتياطية.
        $this->migrator->add('general.app_name', [
            'ar' => 'كود المستقبل',
            'en' => 'Future Code',
        ]);

        $this->migrator->add('general.app_description', [
            'ar' => 'لوحة تحكم كود المستقبل',
            'en' => 'Future Code admin panel',
        ]);

        $this->migrator->add('general.support_email', 'support@futurecode.example');
        $this->migrator->add('general.support_phone', '');

        $this->migrator->add('general.default_locale', 'ar');
        $this->migrator->add('general.timezone', 'Africa/Cairo');

        // ⚠️ بيفضل false افتراضياً: إعداد بيقفل التطبيق مايبدأش مقفول.
        $this->migrator->add('general.maintenance_mode', false);

        $this->migrator->add('general.maintenance_message', [
            'ar' => 'اللوحة تحت الصيانة دلوقتي. جرّب كمان شوية.',
            'en' => 'The panel is under maintenance. Please try again shortly.',
        ]);
    }
};
