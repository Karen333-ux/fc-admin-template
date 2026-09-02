<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Src\Support\Infrastructure\Persistence\Concerns\BelongsToTenant;
use Src\Support\Infrastructure\Persistence\Concerns\InteractsWithResolvedMedia;

/**
 * موديل اختبار بس — صاحب وسائط تابع لمستأجر.
 *
 * الشريحة دي بتسلّم **البنية التحتية للتخزين** (DiskResolver + المسارات +
 * الملكية)، ولسه مافيش موديل إنتاجي بيرفع ملفات: ربط الوسائط بـ `User`
 * (`docs/04` بند ٥ و٧) بيلمس سياق Identity، وده بره النطاق المسموح.
 *
 * نفس أسلوب `TenantOwnedRecord` في شريحة ١: الآلية بتتجرّب على فيكستشر
 * لحد ما يبقى فيه صاحب وسائط إنتاجي.
 */
final class MediaOwner extends Model implements HasMedia
{
    use BelongsToTenant;
    use InteractsWithMedia;
    use InteractsWithResolvedMedia;

    protected $table = 'media_owners';

    /** @var list<string> */
    protected $fillable = ['name'];
}
