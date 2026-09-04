<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Logging;

/**
 * بيشيل الحقول الحسّاسة من أي مصفوفة سياق. (docs/11 بند ٣)
 *
 * مستخرجة من `ContextProcessor` عشان تنضيف اللوج البنيوي (Slice 4.2)
 * وسجل النشاط (Slice 4.3) يقروا من نفس `config('logging.redact')` —
 * نقطة اختناق واحدة أضمن من قايمتين مختلفتين ممكن يتفرّقوا بمرور الوقت.
 *
 * ⚠️ بيمشي على المصفوفات المتداخلة كمان: `['user' => ['password' => ...]]`
 *    شكل شائع لما حد يسجّل حمولة طلب أو خصائص نموذج كاملة.
 */
final readonly class Redactor
{
    /**
     * @param  array<array-key, mixed>  $context
     * @return array<array-key, mixed>
     */
    public function redact(array $context): array
    {
        /** @var list<string> $secrets */
        $secrets = config('logging.redact', []);

        $redacted = [];

        foreach ($context as $key => $value) {
            if (is_string($key) && in_array(mb_strtolower($key), $secrets, true)) {
                $redacted[$key] = '[REDACTED]';

                continue;
            }

            $redacted[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $redacted;
    }
}
