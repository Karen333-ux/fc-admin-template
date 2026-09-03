<?php

declare(strict_types=1);

namespace Src\Support\Domain\Exceptions;

/**
 * إشعار اتبعت بمفتاح مش في `config/notifications.php`. (docs/09 بند ٣)
 *
 * مابيوقفش الإرسال — الحلّال بيرجع لـ `['database']` عشان الإشعار مايضيعش،
 * بس بيتسجّل عشان الكتالوج الناقص يتصلّح.
 */
final class UnknownNotificationKeyException extends DomainException
{
    public static function for(string $key): self
    {
        return new self(
            "مفتاح إشعار مش معرّف في الكتالوج: «{$key}». ضيفه في config/notifications.php."
        );
    }
}
