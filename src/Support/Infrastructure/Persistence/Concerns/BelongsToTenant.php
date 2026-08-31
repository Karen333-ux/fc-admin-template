<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Persistence\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Domain\Exceptions\MissingTenantContextException;
use Src\Support\Domain\Models\Tenant;
use Src\Support\Infrastructure\Tenancy\TenantScope;

/**
 * كل موديل فيه عمود tenant_id بيستخدم الـ trait ده.
 *
 * اللي **مابيستخدموش**: Tenant (بيعرّف الحد) و User (بيعبر الحد) — ADR-002.
 * `tenant_id` **مايدخلش** $fillable — الـ trait هو اللي بيحطه. (docs/22 بند ٦)
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('tenant_id') !== null) {
                return;
            }

            $tenantId = app(TenantContext::class)->id();

            if ($tenantId === null) {
                throw new MissingTenantContextException(static::class);
            }

            $model->setAttribute('tenant_id', $tenantId);
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
