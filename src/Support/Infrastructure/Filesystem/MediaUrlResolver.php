<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Filesystem;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Src\Support\Application\Contracts\DiskResolver;

/**
 * رابط الملف — عام أو مؤقت حسب المجموعة. (docs/04 بند ٦)
 *
 * بيتحقق من ملكية المستأجر **الأول**: طلب رابط لملف مستأجر تاني بيدّي 404.
 */
final readonly class MediaUrlResolver
{
    public function __construct(
        private DiskResolver $disks,
        private MediaOwnership $ownership,
    ) {}

    public function url(Media $media, string $conversion = ''): string
    {
        $this->ownership->assertCurrentTenant($media);

        if (! $this->disks->isPrivate($media->collection_name)) {
            return $conversion !== '' ? $media->getUrl($conversion) : $media->getUrl();
        }

        return $this->temporaryUrl($media, $conversion);
    }

    private function temporaryUrl(Media $media, string $conversion): string
    {
        // المفتاح الحقيقي في الباكدج المثبّت هو temporary_url_default_lifetime
        // — `docs/04` بند ٦ بيكتبه temporary_url_minutes وده مش موجود.
        // ⚠️ max(1, ...): الإعداد جاي من البيئة، وقيمة مش رقمية أو صفر بتدي
        //    (int) 0 ← يعني رابط منتهي من لحظة توليده. الحد الأدنى
        //    دقيقة واحدة عشان الرابط يفضل صالح لفترة محدودة لكن حقيقية.
        $minutes = max(1, (int) config('media-library.temporary_url_default_lifetime', 5));

        // ⚠️ مابنرجعش رابط عام لمجموعة خاصة **أبداً**. لو الديسك مابيدعمش
        // الروابط المؤقتة (public مثلاً — مفيش فيه serve)، بنرمي — لأن البديل الصامت هو كشف
        // ملف خاص برابط دائم.
        //
        // local بيوفّرها لوحده لما serve => true — مفيش route يدوي مطلوب (ADR-022 بند ٤).
        if (! Storage::disk($media->disk)->providesTemporaryUrls()) {
            throw new RuntimeException(
                "الديسك «{$media->disk}» مابيدعمش الروابط المؤقتة، والمجموعة "
                ."«{$media->collection_name}» خاصة. اظبط ديسك بيدعمها (S3) أو "
                .'اعمل route موقّع — docs/04 بند ٦.'
            );
        }

        return $media->getTemporaryUrl(now()->addMinutes($minutes), $conversion);
    }
}
