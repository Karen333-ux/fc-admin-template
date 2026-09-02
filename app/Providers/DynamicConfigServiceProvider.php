<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelSettings\Exceptions\MissingSettings;
use Src\Contexts\Settings\Domain\Settings\MailSettings;

/**
 * بيحقن الإعدادات المحفوظة في كونفيج Laravel وقت التشغيل. (docs/05 بند ٦)
 *
 * ده اللي بيخلّي تغيير إعدادات البريد من اللوحة يأثّر على الرسالة الجاية
 * **من غير ديبلوي** — الكونفيج بيتبني من قاعدة البيانات كل طلب.
 *
 * ⚠️ المصيدة الأولى (موثّقة): بيعمل استعلام في كل طلب. كاش الإعدادات
 *    (`settings.cache.enabled` على Redis) هو اللي بيخلّيه مقبول.
 *
 * ⚠️ المصيدة التانية (موثّقة): لو جدول `settings` لسه ماتعملش، المزوّد ده
 *    بيكسر **كل** أوامر artisan — بما فيها `migrate` نفسه، فالمشروع
 *    مايبقاش قابل للتثبيت أصلاً. الحارس تحت إلزامي.
 */
final class DynamicConfigServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->settingsTableExists()) {
            return;
        }

        $this->applyMailConfig();
    }

    /**
     * هل ينفع نقرا الإعدادات دلوقتي؟
     *
     * بيغطّي تلات حالات حقيقية: الجدول لسه ماتعملش (أول `migrate`)،
     * وقاعدة البيانات مش متاحة أصلاً (بناء الصورة / `config:cache` في CI)،
     * والاتنين لازم يعدّوا بهدوء بدل ما يوقّفوا الإقلاع.
     */
    public function settingsTableExists(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (QueryException) {
            return false;
        }
    }

    /**
     * بيدهس كونفيج البريد بالقيم المحفوظة.
     *
     * ⚠️ `MissingSettings` بتحصل لما الجدول موجود بس هجرة مجموعة `mail`
     *    لسه ماتشغلتش — حالة حقيقية بين هجرتين. الكونفيج بيفضل بتاع
     *    `config/mail.php` وقتها، وده احتياطي صالح مش فشل.
     */
    public function applyMailConfig(): void
    {
        // ⚠️ `Settings` بيحمّل قيمه **بالكسل**: `__get()` هو اللي بينادي
        //    `loadValues()`. يعني `app(MailSettings::class)` لوحدها
        //    مابترميش — الرمي بيحصل عند أول قراءة خاصية. عشان كده بناء
        //    المصفوفة كله جوّه الـ try، مش الحل بس.
        //
        //    الغلطة دي بالظبط خلّت `php artisan migrate` نفسه يقع لما
        //    هجرة مجموعة mail لسه ماتشغلتش.
        try {
            $config = $this->mailConfig(app(MailSettings::class));
        } catch (MissingSettings) {
            return;
        } catch (DecryptException) {
            // ⚠️ سر مش قابل لفكّ التشفير — غالباً APP_KEY اتغيّر، أو صف
            //    اتزرع بـ `add` بدل `addEncrypted`. الرمي هنا **بيوقّف كل
            //    أوامر artisan**، يعني مافيش طريقة تصلّح الصف أصلاً.
            //    بنرجع لكونفيج `config/mail.php` وبنسجّل تحذير — من غير
            //    ما نكتب أي جزء من السر في اللوج.
            Log::warning('Mail settings could not be decrypted; falling back to config/mail.php. Check APP_KEY.');

            return;
        }

        config($config);
    }

    /**
     * @return array<string, mixed>
     */
    private function mailConfig(MailSettings $mail): array
    {
        return [
            'mail.default' => $mail->driver,
            'mail.mailers.smtp.host' => $mail->host,
            'mail.mailers.smtp.port' => $mail->port,
            'mail.mailers.smtp.username' => $mail->username,
            'mail.mailers.smtp.password' => $mail->password,
            // '' في الإعدادات = من غير تشفير؛ Laravel بيتوقّع null مش ''
            'mail.mailers.smtp.encryption' => $mail->encryption !== '' ? $mail->encryption : null,
            'mail.from.address' => $mail->from_address,
            'mail.from.name' => $this->fromName($mail),
        ];
    }

    /**
     * اسم المُرسِل باللغة الحالية، مع الرجوع للاحتياطية وبعدها لاسم التطبيق.
     */
    private function fromName(MailSettings $mail): string
    {
        $locale = app()->getLocale();
        $fallback = (string) config('app.fallback_locale');

        $name = (string) ($mail->from_name[$locale] ?? $mail->from_name[$fallback] ?? '');

        return $name !== '' ? $name : (string) config('app.name');
    }
}
