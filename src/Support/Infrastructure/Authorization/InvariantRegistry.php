<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Authorization;

use Illuminate\Support\Facades\Gate;

/**
 * قواعد السلامة اللي المدير العام مابيتخطاهاش. (docs/19 بند ٥)
 */
final class InvariantRegistry
{
    /** هل القدرة دي محمية بقاعدة سلامة على الموديل ده؟ */
    public function guards(string $ability, mixed $argument): bool
    {
        if ($argument === null) {
            return false;
        }

        $policy = Gate::getPolicyFor($argument);

        return $policy instanceof Policy && $policy->isInvariant($ability);
    }
}
