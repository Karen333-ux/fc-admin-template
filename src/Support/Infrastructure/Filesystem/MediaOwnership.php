<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Filesystem;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Src\Support\Application\Contracts\TenantContext;

/**
 * حدّ المستأجر على الوسائط.
 *
 * جدول `media` بتاع spatie **مافيهوش** `tenant_id` ومابيستخدمش
 * `BelongsToTenant` — فمفيش `TenantScope` بيحميه. الملكية متسجّلة في
 * `custom_properties` وقت الإرفاق، والفحص هنا هو الطبقة اللي بتمنع الوصول
 * بالـ id أو بالمسار.
 *
 * بنرمي `ModelNotFoundException` مش استثناء صلاحيات: عبور حد المستأجر بيدّي
 * **404 مش 403** — «ممنوع» بتأكد إن السجل موجود وده تسريب. (ADR-005)
 */
final readonly class MediaOwnership
{
    public const TENANT_PROPERTY = 'tenant_id';

    public function __construct(private TenantContext $context) {}

    /**
     * الخصائص اللي بتتحط على كل ملف وقت الإرفاق.
     *
     * @return array<string, int|null>
     */
    public function properties(): array
    {
        return [self::TENANT_PROPERTY => $this->context->id()];
    }

    public function belongsToCurrentTenant(Media $media): bool
    {
        $owner = $media->getCustomProperty(self::TENANT_PROPERTY);
        $current = $this->context->id();

        // ملف عام (مش تابع لمستأجر) — متاح لأي سياق
        if ($owner === null) {
            return true;
        }

        return $current !== null && (int) $owner === $current;
    }

    /** @throws ModelNotFoundException */
    public function assertCurrentTenant(Media $media): void
    {
        if ($this->belongsToCurrentTenant($media)) {
            return;
        }

        throw (new ModelNotFoundException)->setModel($media::class, [$media->getKey()]);
    }
}
