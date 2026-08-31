<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;

if (! function_exists('permission_label')) {
    /**
     * اسم صلاحية مقروء للمستخدم.
     *
     * الصفحات والودجتس ليها كتالوج منفصل، ومفتاحها بيتبحث **كامل** مش مقسّم.
     * لازم يتفحصوا الأول: 'access.dashboard' فيه فاصل، فالتقسيم هيلاقي شقين
     * ويعدّي على فرع المورد ويرجّع مفاتيح ترجمة خام. (ADR-003)
     */
    function permission_label(string $permission): string
    {
        foreach (['pages', 'widgets'] as $catalog) {
            $key = "authorization.{$catalog}.{$permission}";

            if (Lang::has($key)) {
                return __($key);
            }
        }

        [$action, $resource] = array_pad(
            explode((string) config('authorization.separator'), $permission, 2),
            2,
            null,
        );

        if ($resource === null) {
            // مفيش ترجمة ومفيش فاصل — رجّع الاسم الخام بدل مفتاح مكسور
            return $permission;
        }

        return __('authorization.actions.'.$action).' — '.__('authorization.resources.'.$resource);
    }
}
