<?php

declare(strict_types=1);

namespace Src\Support\Domain\Exceptions;

/**
 * محاولة تبديل للغة مش في `config('app.supported_locales')`.
 *
 * بنرمي بدل ما نتجاهل: القيمة جاية من الطلب، والتجاهل الصامت بيخلّي
 * المستخدم يضغط ويحصل مفيش حاجة من غير ما يعرف ليه.
 */
final class UnsupportedLocaleException extends DomainException
{
    /** @param list<string> $supported */
    public static function for(string $locale, array $supported): self
    {
        return new self(
            "لغة مش مدعومة: «{$locale}». المدعوم: ".implode('، ', $supported).'.'
        );
    }
}
