<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Filesystem;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * عزل المستأجرين على مستوى المسار. (docs/04 بند ٤)
 *
 * ⚠️ **ده مش بديل عن فحص الصلاحية.** المسار بيمنع التصادم والخلط، بس مايمنعش
 * حد يجيب Media بالـ id لو الكود مافحصش الملكية. الفحص ده في
 * `MediaOwnership`. الاتنين مطلوبين.
 *
 * التوقيعات متطابقة مع الواجهة المثبّتة (اتفحصت في
 * `vendor/spatie/laravel-medialibrary/src/Support/PathGenerator/PathGenerator.php`
 * زي ما `docs/04` بند ٤ بيطلب).
 */
final class TenantAwarePathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        $tenantId = $media->getCustomProperty(MediaOwnership::TENANT_PROPERTY);

        // القيم اللي مش أرقام بتروح لـ global — مايتحطش مدخل مستخدم في المسار
        $segment = is_int($tenantId) || (is_string($tenantId) && ctype_digit($tenantId))
            ? (string) $tenantId
            : 'global';

        return "tenants/{$segment}/{$media->collection_name}/{$media->getKey()}/";
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media).'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media).'responsive/';
    }
}
