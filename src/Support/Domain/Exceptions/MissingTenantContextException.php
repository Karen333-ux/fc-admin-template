<?php

declare(strict_types=1);

namespace Src\Support\Domain\Exceptions;

/**
 * استعلام على موديل تابع لمستأجر من غير سياق مستأجر.
 *
 * الافتراضي هو الرفض: من غير سياق بنرمي، مانرجّعش كل الصفوف. (docs/22 بند ٥)
 */
final class MissingTenantContextException extends DomainException
{
    public function __construct(public readonly string $modelClass)
    {
        parent::__construct("استعلام على {$modelClass} من غير سياق مستأجر.");
    }
}
