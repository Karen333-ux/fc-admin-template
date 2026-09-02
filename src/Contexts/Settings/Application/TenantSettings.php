<?php

declare(strict_types=1);

namespace Src\Contexts\Settings\Application;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Spatie\LaravelSettings\Settings;
use Src\Contexts\Settings\Domain\Models\TenantSetting;
use Src\Support\Application\Contracts\TenantContext;

/**
 * الطبقة التانية فوق إعدادات spatie العامة. (docs/05 بند ٤)
 *
 * `spatie/laravel-settings` مفيهوش دعم مستأجرين، فالقراءة بتبص على إعداد
 * المستأجر الأول وبترجع للعام لو مالقتش.
 *
 * ⚠️ مفيش `where('tenant_id')` ولا `tenant_id` في mass-assignment — `TenantScope`
 * و`BelongsToTenant` بيعملوا الاتنين. (ADR-020)
 */
final readonly class TenantSettings
{
    public function __construct(
        private TenantContext $context,
        private CacheRepository $cache,
    ) {}

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $tenantId = $this->context->id();

        // من غير سياق مفيش إعداد مستأجر نقراه — بنرجع للعام.
        // مابنستعلمش أصلاً، فـ TenantScope مابيرميش هنا.
        if ($tenantId === null) {
            return $this->global($group, $key, $default);
        }

        return $this->cache
            ->tags($this->tagsFor($tenantId))
            ->rememberForever(
                $this->cacheKey($tenantId, $group, $key),
                function () use ($group, $key, $default): mixed {
                    $row = TenantSetting::query()
                        ->where('group', $group)
                        ->where('key', $key)
                        ->first();

                    // ?? بيلقط الـ null سواء الصف مش موجود أو قيمته null
                    return $row->value ?? $this->global($group, $key, $default);
                },
            );
    }

    public function set(string $group, string $key, mixed $value): void
    {
        TenantSetting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value],
        );

        $this->forget();
    }

    /** يمسح كاش إعدادات المستأجر الحالي بس */
    public function forget(): void
    {
        $tenantId = $this->context->id();

        if ($tenantId === null) {
            return;
        }

        $this->cache->tags($this->tagsFor($tenantId))->flush();
    }

    /**
     * القيمة العامة من كلاس spatie المسجّل للمجموعة دي.
     *
     * الربط بين المجموعة والكلاس بييجي من `config('settings.settings')` —
     * مفيش خريطة مكتوبة في الكود. (CLAUDE.md بند ١)
     */
    private function global(string $group, string $key, mixed $default): mixed
    {
        $class = $this->globalSettingsClass($group);

        if ($class === null) {
            return $default;
        }

        $settings = app($class);

        return property_exists($settings, $key) ? $settings->{$key} : $default;
    }

    /** @return class-string<Settings>|null */
    private function globalSettingsClass(string $group): ?string
    {
        /** @var list<class-string<Settings>> $registered */
        $registered = config('settings.settings', []);

        foreach ($registered as $class) {
            if (is_subclass_of($class, Settings::class) && $class::group() === $group) {
                return $class;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function tagsFor(int $tenantId): array
    {
        // العزل بالـ tags — مش بـ cache.prefix. (ADR-008)
        return ['settings', "tenant:{$tenantId}"];
    }

    private function cacheKey(int $tenantId, string $group, string $key): string
    {
        return "settings:{$tenantId}:{$group}:{$key}";
    }
}
