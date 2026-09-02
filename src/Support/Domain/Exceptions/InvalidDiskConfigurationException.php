<?php

declare(strict_types=1);

namespace Src\Support\Domain\Exceptions;

/**
 * ديسك متطلوب مش معرّف في `config/filesystems.php`.
 *
 * بنرمي بدل ما نرجع لديسك تاني: الرجوع الصامت من S3 لـ local معناه إن ملفات
 * كان المفروض تروح للتخزين السحابي بتتكتب على القرص المحلي من غير ما حد ياخد
 * باله — وبعدين بتضيع مع أول نشر.
 */
final class InvalidDiskConfigurationException extends DomainException
{
    public function __construct(public readonly string $disk, string $reason)
    {
        parent::__construct("ديسك التخزين «{$disk}» {$reason}.");
    }

    public static function notDefined(string $disk): self
    {
        return new self($disk, 'مش معرّف في config/filesystems.php');
    }

    public static function empty(string $setting): self
    {
        return new self('(فاضي)', "مطلوب في الإعداد «{$setting}» بس قيمته فاضية");
    }
}
