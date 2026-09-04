<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Infrastructure\Http;

/**
 * تحليل بسيط لـ User-Agent — بره النطاق تركيب حزمة كاملة (docs/12 بند ٢
 * مابيطلبش واحدة صراحةً). أفضل مجهود لعائلات المتصفح/النظام الشائعة —
 * مش دقيق ١٠٠٪، وده مقبول لعرض معلوماتي بس، مش قرار أمني.
 *
 * ⚠️ ترتيب الفحص مهم: يوزر-إيجنت Edge وChrome بيحتوي كلمة "Safari"، ويوزر-إيجنت
 *    Chrome بيحتوي "Safari" وEdge بيحتوي "Chrome" — لازم الأخص يتفحص الأول.
 */
final readonly class UserAgentParser
{
    /**
     * @return array{platform: ?string, browser: ?string, device_name: ?string}
     */
    public function parse(?string $userAgent): array
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return ['platform' => null, 'browser' => null, 'device_name' => null];
        }

        $platform = $this->platform($userAgent);
        $browser = $this->browser($userAgent);

        $deviceName = match (true) {
            $browser !== null && $platform !== null => "{$browser} — {$platform}",
            $browser !== null => $browser,
            $platform !== null => $platform,
            default => null,
        };

        return ['platform' => $platform, 'browser' => $browser, 'device_name' => $deviceName];
    }

    private function platform(string $userAgent): ?string
    {
        return match (true) {
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS X') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };
    }

    private function browser(string $userAgent): ?string
    {
        return match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/'), str_contains($userAgent, 'Opera') => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => null,
        };
    }
}
