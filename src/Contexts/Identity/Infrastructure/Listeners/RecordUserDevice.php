<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Infrastructure\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Infrastructure\Http\UserAgentParser;
use Src\Contexts\Identity\Infrastructure\Notifications\NewDeviceLoginNotification;

/**
 * بيسجّل/يحدّث الجهاز عند كل دخول. (docs/12 بند ٢)
 *
 * ⚠️ المطابقة بـ (user_id, platform, browser, ip_address) مش بـ session_id —
 *    Laravel بيعيد توليد الـ session ID عند كل دخول (حماية من session
 *    fixation)، فلو اتطابقنا بيه كان كل دخول هيبقى «جهاز جديد» دايماً،
 *    وده بيبطّل فكرة التعرّف على الجهاز خالص. مش مذكورة بالتفصيل في
 *    docs/12 — تبنّي تقني محلي، مش قرار معماري يستاهل ADR.
 *
 * ⚠️ إشعار الجهاز الجديد بيتبعت **بس** لما مفيش تطابق قديم — تسجيل دخول
 *    متكرر من نفس الجهاز مابيغرقش صندوق الوارد.
 *
 * ⚠️ لو الـ request مالهوش جلسة (`Auth::login()` بره سياق HTTP كامل —
 *    بيحصل في اختبارات الصفحات القائمة اللي بتنادي `Auth::login()`
 *    مباشرة من غير `StartSession`) بنسجّل الجهاز من غير `session_id`
 *    بدل ما نرمي استثناء. الحدث دايماً عنده جلسة في الإنتاج الحقيقي
 *    (اللوحة كلها ورا `StartSession`)، فده حارس دفاعي مش سلوك متوقّع.
 */
final class RecordUserDevice
{
    public function __construct(private readonly UserAgentParser $parser) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $request = request();
        $parsed = $this->parser->parse($request->userAgent());
        $ip = $request->ip() ?? '0.0.0.0';
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;

        $existing = $user->devices()
            ->where('platform', $parsed['platform'])
            ->where('browser', $parsed['browser'])
            ->where('ip_address', $ip)
            ->first();

        $now = Carbon::now();

        if ($existing !== null) {
            $existing->update([
                'session_id' => $sessionId,
                'last_active_at' => $now,
            ]);

            return;
        }

        $user->devices()->create([
            'session_id' => $sessionId,
            'device_name' => $parsed['device_name'],
            'platform' => $parsed['platform'],
            'browser' => $parsed['browser'],
            'ip_address' => $ip,
            'last_active_at' => $now,
        ]);

        $user->notify(new NewDeviceLoginNotification(
            platform: $parsed['platform'],
            browser: $parsed['browser'],
            ipAddress: $ip,
            occurredAt: $now,
        ));
    }
}
