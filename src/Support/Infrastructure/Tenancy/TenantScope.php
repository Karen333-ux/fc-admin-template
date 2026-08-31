<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Domain\Exceptions\MissingTenantContextException;

/**
 * الحماية الحقيقية. الافتراضي = الرفض. (docs/22 بند ٥)
 *
 * @implements Scope<Model>
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);
        $tenantId = $context->id();

        if ($tenantId === null) {
            // من غير سياق بنرمي، مانرجّعش كل الصفوف.
            if (! $context->isBypassed()) {
                throw new MissingTenantContextException($model::class);
            }

            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
