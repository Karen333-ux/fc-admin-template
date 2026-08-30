# ٠٤ — نظام الملفات والوسائط

## المبدأ

**الديسك اللي بنخزّن عليه قرار وقت التشغيل، مش وقت الكتابة.** الكود مش بيعرف S3 ولا local — بيسأل `DiskResolver` وخلاص.

ليه؟ عشان نقدر:
- نشتغل local في التطوير، MinIO في staging، S3 في الإنتاج — بنفس الكود
- ننقل مستأجر معيّن لديسك مختلف من لوحة الإعدادات
- نفصل الملفات العامة عن الخاصة
- ننقل من S3 لـ DigitalOcean Spaces بتغيير سطر في الإعدادات

---

## ١. التثبيت

```bash
composer require spatie/laravel-medialibrary:^11.23
composer require filament/spatie-laravel-media-library-plugin:"^5.7"
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-config"
php artisan migrate
```

---

## ٢. تعريف الديسكات

`config/filesystems.php`:

```php
'disks' => [

    'local' => [
        'driver' => 'local',
        'root'   => storage_path('app/private'),
        'throw'  => true,
    ],

    'public' => [
        'driver'     => 'local',
        'root'       => storage_path('app/public'),
        'url'        => env('APP_URL') . '/storage',
        'visibility' => 'public',
        'throw'      => true,
    ],

    // الملفات العامة (لوجوهات، صور الملف الشخصي)
    's3' => [
        'driver'                  => 's3',
        'key'                     => env('AWS_ACCESS_KEY_ID'),
        'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
        'region'                  => env('AWS_DEFAULT_REGION'),
        'bucket'                  => env('AWS_BUCKET'),
        'url'                     => env('AWS_URL'),
        'endpoint'                => env('AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        'visibility'              => 'public',
        'throw'                   => true,
    ],

    // الملفات الخاصة (عقود، مستندات، هويات) — روابط مؤقتة فقط
    's3-private' => [
        'driver'                  => 's3',
        'key'                     => env('AWS_ACCESS_KEY_ID'),
        'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
        'region'                  => env('AWS_DEFAULT_REGION'),
        'bucket'                  => env('AWS_PRIVATE_BUCKET', env('AWS_BUCKET')),
        'endpoint'                => env('AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        'visibility'              => 'private',
        'throw'                   => true,
    ],
],
```

---

## ٣. الـ DiskResolver (قلب النظام)

الواجهة في `src/Support/Application/Contracts/DiskResolver.php`:

```php
namespace Src\Support\Application\Contracts;

interface DiskResolver
{
    /** الديسك المناسب لمجموعة وسائط معيّنة */
    public function for(string $collection): string;

    /** ديسك التحويلات (المصغّرات) */
    public function forConversions(string $collection): string;

    /** هل المجموعة دي خاصة (تحتاج رابط مؤقت)؟ */
    public function isPrivate(string $collection): bool;
}
```

التنفيذ في `src/Support/Infrastructure/Filesystem/SettingsDrivenDiskResolver.php`:

```php
namespace Src\Support\Infrastructure\Filesystem;

final class SettingsDrivenDiskResolver implements DiskResolver
{
    public function __construct(
        private readonly StorageSettings $settings,
    ) {}

    public function for(string $collection): string
    {
        // ١. تجاوز صريح لمجموعة معيّنة من الإعدادات
        if ($override = $this->settings->collection_disks[$collection] ?? null) {
            return $override;
        }

        // ٢. المجموعات الخاصة
        if ($this->isPrivate($collection)) {
            return $this->settings->private_disk;
        }

        // ٣. الافتراضي
        return $this->settings->default_disk;
    }

    public function forConversions(string $collection): string
    {
        // التحويلات دايماً على ديسك عام (المصغّرات مش سرية)
        return $this->settings->conversions_disk ?: $this->for($collection);
    }

    public function isPrivate(string $collection): bool
    {
        return in_array($collection, $this->settings->private_collections, true);
    }
}
```

الربط في `AppServiceProvider`:

```php
$this->app->singleton(DiskResolver::class, SettingsDrivenDiskResolver::class);
```

الاستخدام:

```php
$user->addMedia($file)
    ->toMediaCollection('avatars', app(DiskResolver::class)->for('avatars'));
```

اعمل trait مساعد عشان محدش ينسى:

```php
trait InteractsWithResolvedMedia
{
    public function attachMedia(
        string|UploadedFile $file,
        string $collection,
    ): Media {
        $resolver = app(DiskResolver::class);

        return $this->addMedia($file)
            ->storingConversionsOnDisk($resolver->forConversions($collection))
            ->toMediaCollection($collection, $resolver->for($collection));
    }
}
```

---

## ٤. مولّد المسارات (PathGenerator) — عزل المستأجرين

```php
namespace Src\Support\Infrastructure\Filesystem;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

final class TenantAwarePathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        $tenantId = $media->getCustomProperty('tenant_id') ?? 'global';

        return "tenants/{$tenantId}/{$media->collection_name}/{$media->getKey()}/";
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media) . 'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media) . 'responsive/';
    }
}
```

في `config/media-library.php`:

```php
'path_generator' => \Src\Support\Infrastructure\Filesystem\TenantAwarePathGenerator::class,
```

> ⚠️ **تحقّق من العقد:** تأكد من التوقيعات الفعلية للواجهة `Spatie\MediaLibrary\Support\PathGenerator\PathGenerator` في نسخة الباكدج المثبّتة قبل ما تعتمد على الكود ده حرفياً. افتح `vendor/spatie/laravel-medialibrary/src/Support/PathGenerator/PathGenerator.php`.

حقن `tenant_id` تلقائياً — في مستمع على حدث `MediaHasBeenAddedEvent` أو بـ `->withCustomProperties()`:

```php
$this->addMedia($file)
    ->withCustomProperties(['tenant_id' => app(TenantContext::class)->id()])
    ->toMediaCollection($collection, $resolver->for($collection));
```

---

## ٥. مجموعات الوسائط والتحويلات

```php
class User extends Model implements HasMedia
{
    use InteractsWithMedia, InteractsWithResolvedMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useDisk(app(DiskResolver::class)->for('avatar'));

        $this->addMediaCollection('documents')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png'])
            ->useDisk(app(DiskResolver::class)->for('documents'));
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 128, 128)
            ->format('webp')
            ->quality(82)
            ->performOnCollections('avatar')
            ->queued();          // ← افتراضي، بس اكتبه صريح للوضوح

        $this->addMediaConversion('preview')
            ->fit(Fit::Max, 800, 800)
            ->format('webp')
            ->quality(85)
            ->performOnCollections('avatar', 'documents')
            ->queued();
    }
}
```

> **قاعدة:** كل التحويلات `->queued()`. مفيش `->nonQueued()` غير لو المصغّرة مطلوبة فوراً في نفس الطلب — ولو محتاجينها فوراً، غالباً التصميم غلط.

---

## ٦. الروابط المؤقتة للملفات الخاصة

```php
final class MediaUrlResolver
{
    public function __construct(private readonly DiskResolver $disks) {}

    public function url(Media $media, string $conversion = ''): string
    {
        if (! $this->disks->isPrivate($media->collection_name)) {
            return $conversion ? $media->getUrl($conversion) : $media->getUrl();
        }

        return $media->getTemporaryUrl(
            now()->addMinutes(config('media-library.temporary_url_minutes', 10)),
            $conversion,
        );
    }
}
```

> `getTemporaryUrl()` بيشتغل على S3 بس. للـ local disk اعمل route موقّع (`URL::temporarySignedRoute`) كـ fallback.

---

## ٧. مكوّن Filament

```php
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

SpatieMediaLibraryFileUpload::make('avatar')
    ->label(__('identity.fields.avatar'))
    ->collection('avatar')
    ->disk(fn () => app(DiskResolver::class)->for('avatar'))
    ->conversion('thumb')
    ->image()
    ->imageEditor()
    ->imageEditorAspectRatios(['1:1'])
    ->maxSize(2048)
    ->downloadable()
    ->openable()
    ->reorderable()
    ->helperText(__('media.help.avatar'))
    ->visible(fn (?Model $record) => auth()->user()->can('attachMedia', $record ?? static::getModel()));
```

> ⚠️ **مصيدة أداء معروفة:** رفع ملفات كبيرة لـ S3 عبر المكوّن ده بيمرّ بالسيرفر بتاعنا الأول (Livewire temp upload → السيرفر → S3). للملفات فوق ١٠ ميجا استخدم رفع مباشر (presigned URL) بدل ده. راجع issue #14542 في ريبو Filament.

---

## ٨. صفحة إعدادات التخزين

في Filament — `StorageSettingsPage` تسمح بـ:
- اختيار الديسك الافتراضي (dropdown من `array_keys(config('filesystems.disks'))`)
- اختيار ديسك الملفات الخاصة
- تحديد المجموعات الخاصة (multi-select)
- الحد الأقصى لحجم الملف
- الامتدادات المسموحة
- زرار **«اختبار الاتصال»** — يرفع ملف تجريبي ويحذفه ويعرض النتيجة

الصفحة محمية بـ `settings.manage_storage`. التفاصيل في `docs/05-settings.md`.

---

## ٩. نقل الوسائط بين الديسكات

أمر لازم يكون موجود:

```bash
php artisan media:move --from=local --to=s3 --collection=avatar --tenant=3 --chunk=100
```

يشتغل على دفعات، يتحقق من الـ checksum بعد النقل، ويحدّث `disk_name` في جدول `media` **بعد** التأكد من نجاح النسخ. مفيش حذف من المصدر غير بـ `--delete-source` صريح.

---

## ١٠. معايير القبول

- [ ] مفيش `'s3'` أو `'local'` مكتوب في أي كود بره `config/` و`DiskResolver`
- [ ] تغيير الديسك الافتراضي من صفحة الإعدادات بيأثر على الرفع الجديد فوراً
- [ ] ملف في مجموعة خاصة رابطه مؤقت وبينتهي
- [ ] مسارات الملفات مقسّمة `tenants/{id}/...` — مؤكد من كونسول MinIO
- [ ] التحويلات بتتنفّذ في الطابور — Horizon بيعرضها
- [ ] `php artisan media:move` شغّال ومختبر
- [ ] زرار «اختبار الاتصال» في صفحة الإعدادات شغّال
- [ ] اختبار Pest: رفع ملف لمستأجر أ مش قابل للوصول من مستأجر ب
