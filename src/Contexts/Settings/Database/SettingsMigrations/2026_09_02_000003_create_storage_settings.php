<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // الافتراضيات دي للتطوير المحلي. الإنتاج بيغيّرها من صفحة إعدادات
        // التخزين (docs/04 بند ٨ — لسه ماتعملتش) أو بـ tinker.
        $this->migrator->add('storage.default_disk', 'public');
        $this->migrator->add('storage.private_disk', 'local');
        $this->migrator->add('storage.conversions_disk', 'public');

        $this->migrator->add('storage.private_collections', ['documents', 'contracts']);
        $this->migrator->add('storage.collection_disks', []);

        $this->migrator->add('storage.max_upload_size_kb', 2048);

        // ⚠️ مفيش image/svg+xml. الـ SVG ملف XML بينفّذ سكربت، ورفعه في
        // مجموعة عامة ثغرة XSS مخزّنة. (CLAUDE.md · docs/20)
        $this->migrator->add('storage.allowed_mimes', [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'application/pdf',
        ]);
    }
};
