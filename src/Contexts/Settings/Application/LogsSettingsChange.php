<?php

declare(strict_types=1);

namespace Src\Contexts\Settings\Application;

use function activity;

use Illuminate\Database\Eloquent\Model;

/**
 * بيسجّل تعديل صفحة إعدادات في سجل النشاط. (docs/11 بند ٥)
 *
 * ⚠️ إعدادات `spatie/laravel-settings` **مش موديل Eloquent** —
 *    `LogsActivity` مايتحطش عليها مباشرة (اتفحص السورس: `Settings` كلاس
 *    مجرّد بيطبّق `Arrayable`/`Jsonable`/`Responsable` بس). فالتسجيل هنا
 *    يدوي وصريح — قرار المستخدم النهائي، مفيش آلية تانية بديلة.
 *
 * ⚠️ الفرق بيتحسب على مستوى المفتاح، ومفيش تحويل صفحة الإعدادات لموديل
 *    Eloquent ولا إضافة أعمدة وهمية — القيم بتتقارن زي ما هي من `toArray()`.
 *
 * ⚠️ `log_name = security`: تغيير إعدادات النظام (بريد، تخزين، عام) من
 *    أخطر العمليات — مايتحذفش أبداً بالتقليم الدوري.
 *
 * ⚠️ التنقية بتحصل مركزياً في `AppServiceProvider::enrichActivityLog()`
 *    عبر `Redactor` — مفيش تنقية تانية هنا. كلمة مرور البريد مثلاً بتتشال
 *    تلقائياً لأن `Redactor` بيمشي على `properties` المتداخلة كلها.
 */
final readonly class LogsSettingsChange
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function handle(string $settingsClass, array $before, array $after, ?Model $causer, string $description): void
    {
        $diff = $this->diff($before, $after);

        if ($diff === []) {
            return;
        }

        activity('security')
            ->causedBy($causer)
            ->event('updated')
            ->withProperties([
                'settings' => $settingsClass,
                'old' => array_map(static fn (array $pair) => $pair['old'], $diff),
                'new' => array_map(static fn (array $pair) => $pair['new'], $diff),
            ])
            ->log($description);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function diff(array $before, array $after): array
    {
        $changed = [];

        foreach ($after as $key => $value) {
            $original = $before[$key] ?? null;

            if ($original !== $value) {
                $changed[$key] = ['old' => $original, 'new' => $value];
            }
        }

        return $changed;
    }
}
