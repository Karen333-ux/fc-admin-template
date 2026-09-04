<?php

declare(strict_types=1);

namespace Src\Support\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * الرؤوس الأمنية الثابتة على كل استجابة. (docs/12 بند ٦)
 *
 * ⚠️ HSTS في الإنتاج بس — بالنص الحرفي في الوثيقة: لو اتفعّلت محلياً على
 *    HTTP هتكسر التطوير (المتصفح بيحفظ التوجيه لـ HTTPS لمدة سنة كاملة،
 *    ومفيش رجوع منه من غير مسح إعدادات المتصفح). نفس نمط
 *    `app()->isProduction()` المستخدم في `configurePasswordDefaults()`
 *    (docs/12 بند ٤).
 *
 * ⚠️ متسجّلة على ميدلوير اللوحة نفسها (`AdminPanelProvider`) مش على
 *    `bootstrap/app.php` — نفس مكان كل ميدلوير تاني في الشريحة دي
 *    (`SetLocale`, `AssignRequestContext`). التسجيل في `bootstrap/app.php`
 *    ضمن `$middleware->web()` مذكور في `docs/14` بند ١ كجزء من تجهيز
 *    الإنتاج (`TrustProxies`) — ده أسبوع ٥، مش الشريحة دي.
 *
 * ⚠️ `SESSION_SECURE_COOKIE`/`SESSION_ENCRYPT` المذكورين في نفس بند
 *    docs/12 بند ٦ **مش متغيّرين هنا**: ده إعداد `.env` إنتاج، ومكانه
 *    الفعلي `docs/14` بند ٢ (ملف .env للإنتاج — أسبوع ٥). تغيير القيم
 *    الافتراضية في `.env.example` المستخدم في التطوير المحلي كان هيكسر
 *    مسار HTTP المحلي (منفذ ٨٠٨١ في docs/00) الموثّق كمسار مدعوم حالياً.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        ];

        if (app()->isProduction()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains; preload';
        }

        $response->headers->add($headers);

        return $response;
    }
}
