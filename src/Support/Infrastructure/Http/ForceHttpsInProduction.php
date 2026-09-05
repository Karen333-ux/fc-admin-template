<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Http;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\URL;

/**
 * فرض https على كل رابط بيتولّد وقت الإنتاج بس. (docs/14 بند ١)
 *
 * ⚠️ كلاس مستقل مش سطرين جوّه AppServiceProvider::boot() بالقصد —
 *    `boot()` بيتنفّذ مرة واحدة وقت إقلاع التطبيق، قبل ما أي اختبار يقدر
 *    يبدّل البيئة لـ production. إعادة نداء `boot()` بالكامل تاني (عشان
 *    نختبر الفرع ده) بتفشل فعلياً: `configureHealthChecks()` بتتراكم
 *    (`Health::checks()` بيضيف مش يستبدل)، فبترمي DuplicateCheckNamesFound
 *    من ثاني نداء. كلاس منفصل بيتقدر يتنادى لوحده من غير باقي boot()،
 *    بنفس أسلوب `TagFailedJobForSentry`.
 */
final class ForceHttpsInProduction
{
    public function handle(Application $app): void
    {
        // environment('production') مش isProduction(): الأخيرة مش جزء من
        // عقد Illuminate\Contracts\Foundation\Application، وكانت هتفشل
        // PHPStan لأن الباراميتر متطابق مع العقد مش الكلاس الفعلي.
        if ($app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
