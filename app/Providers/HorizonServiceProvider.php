<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * بوابة الدخول لـ Horizon — بصلاحية access.horizon مش قايمة إيميلات
     * ثابتة. (docs/13 بند ١)
     *
     * ⚠️ `Gate::forUser($user)` مش `Gate::allows()`: `Gate::allows()` بيستخدم
     *    المستخدم المسجّل دخوله حالياً وبيتجاهل `$user` اللي جاي كمعامل —
     *    فبيقع مع الاختبارات (اللي بتفحص مستخدمين مختلفين بره سياق طلب
     *    حقيقي) ومع أي استدعاء يمرّر مستخدم مختلف عن المسجّل حالياً.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn ($user = null) => Gate::forUser($user)->allows('access.horizon'));
    }
}
