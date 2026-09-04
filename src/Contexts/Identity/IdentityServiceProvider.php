<?php

declare(strict_types=1);

namespace Src\Contexts\Identity;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Src\Contexts\Identity\Infrastructure\Listeners\LogFailedLoginActivity;
use Src\Contexts\Identity\Infrastructure\Listeners\LogRoleActivity;
use Src\Contexts\Identity\Infrastructure\Listeners\RecordUserDevice;

final class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/Lang', 'identity');

        $this->registerActivityListeners();
    }

    /**
     * تسجيل تغييرات الأدوار — تصعيد صلاحيات. (docs/11 بند ٥)
     */
    private function registerActivityListeners(): void
    {
        Event::listen(RoleAttachedEvent::class, [LogRoleActivity::class, 'handleAttached']);
        Event::listen(RoleDetachedEvent::class, [LogRoleActivity::class, 'handleDetached']);
        Event::listen(Failed::class, [LogFailedLoginActivity::class, 'handle']);
        // تسجيل/تحديث الجهاز عند كل دخول — docs/12 بند ٢
        Event::listen(Login::class, [RecordUserDevice::class, 'handle']);
    }
}
