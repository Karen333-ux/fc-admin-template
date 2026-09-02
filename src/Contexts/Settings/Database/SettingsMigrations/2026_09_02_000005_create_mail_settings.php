<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // ⚠️ الافتراضي `log` بالقصد: تثبيت جديد مايبعتش بريد حقيقي قبل
        //    ما حد يظبط السيرفر ويراجعه. أول ما يتحوّل لـ smtp، الحقول
        //    تحت بتبقى مطلوبة في الصفحة.
        $this->migrator->add('mail.driver', 'log');

        $this->migrator->add('mail.host', '');
        $this->migrator->add('mail.port', 587);
        $this->migrator->add('mail.username', '');

        // ⚠️ `addEncrypted` مش `add`: الخاصية معلنة في
        //    `MailSettings::encrypted()`، فالقراءة بتحاول تفكّ التشفير.
        //    قيمة مزروعة بـ `add` عادي بترمي `DecryptException` على طول —
        //    يعني اللوحة كلها بتقع، مش حقل البريد بس.
        $this->migrator->addEncrypted('mail.password', '');

        $this->migrator->add('mail.encryption', 'tls');

        $this->migrator->add('mail.from_address', 'no-reply@futurecode.example');
        $this->migrator->add('mail.from_name', [
            'ar' => 'كود المستقبل',
            'en' => 'Future Code',
        ]);
    }
};
