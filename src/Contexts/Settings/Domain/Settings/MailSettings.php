<?php

declare(strict_types=1);

namespace Src\Contexts\Settings\Domain\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * إعدادات البريد — **عامة للتثبيت كله، مش لكل مستأجر**. (docs/05 بند ٤)
 *
 * «التخزين والبريد والأمان **عامة** (يديرها الـ super_admin)». مفيش
 * `tenant_id` ومفيش مرور على `TenantSettings`: مستأجر يقدر يغيّر سيرفر
 * البريد معناه إنه يقدر يوجّه رسايل التثبيت كله لسيرفره هو.
 */
final class MailSettings extends Settings
{
    public string $driver;

    /**
     * ⚠️ مخاطرة SSRF مقبولة وموثّقة: الحقل ده بيتكتب من اللوحة والسيرفر
     * بيتصل بيه. مقصور على `manage_mail.settings` (super_admin/admin) —
     * نفس مستوى التحكم اللي كان في `.env` قبل كده. مفيش allow-list
     * بالقصد (قرار مراجعة الجاهزية، بند ٣).
     */
    public string $host;

    public int $port;

    public string $username;

    /** ⚠️ سر — لازم يفضل في `encrypted()` تحت. */
    public string $password;

    /** '' = من غير تشفير · tls · ssl */
    public string $encryption;

    public string $from_address;

    /** @var array<string, string> اسم المُرسِل مترجم */
    public array $from_name;

    public static function group(): string
    {
        return 'mail';
    }

    /**
     * كلمة مرور SMTP بتتشفّر قبل ما تنزل قاعدة البيانات.
     *
     * ⚠️ `docs/05` بند ٢: «أي إعداد فيه سر لازم يكون في `encrypted()`».
     * الصف في جدول `settings` لازم يبقى نص مشفّر — فيه اختبار بيقرا الصف
     * الخام ويتأكد إن كلمة المرور مش ظاهرة فيه.
     *
     * @return list<string>
     */
    public static function encrypted(): array
    {
        return ['password'];
    }
}
