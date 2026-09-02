<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Persistence\Concerns;

use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Src\Support\Application\Contracts\DiskResolver;
use Src\Support\Infrastructure\Filesystem\MediaOwnership;

/**
 * الطريقة **الوحيدة** لإرفاق ملف. (docs/04 بند ٣)
 *
 * موجود عشان محدش ينسى حاجة من التلاتة:
 * ١. ديسك المجموعة من `DiskResolver` — مش مكتوب في الكود
 * ٢. ديسك التحويلات كمان
 * ٣. `tenant_id` في `custom_properties` — من غيره الملف بيروح `tenants/global/`
 *    ومابيبقاش عليه حدّ مستأجر
 */
trait InteractsWithResolvedMedia
{
    public function attachMedia(string|UploadedFile $file, string $collection): Media
    {
        $disks = app(DiskResolver::class);

        return $this->addMedia($file)
            ->withCustomProperties(app(MediaOwnership::class)->properties())
            ->storingConversionsOnDisk($disks->forConversions($collection))
            ->toMediaCollection($collection, $disks->for($collection));
    }
}
