<?php

declare(strict_types=1);

namespace Src\Contexts\Identity;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Infrastructure\Listeners\LogFailedLoginActivity;
use Src\Contexts\Identity\Infrastructure\Listeners\LogImpersonationActivity;
use Src\Contexts\Identity\Infrastructure\Listeners\LogRoleActivity;
use Src\Contexts\Identity\Infrastructure\Listeners\RecordUserDevice;
use Src\Contexts\Identity\Infrastructure\Observers\UserPasswordHistoryObserver;
use STS\FilamentImpersonate\Events\EnterImpersonation;
use STS\FilamentImpersonate\Events\LeaveImpersonation;

final class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadTranslationsFrom(__DIR__.'/Lang', 'identity');

        $this->registerActivityListeners();

        // تاريخ كلمات المرور والإشعار الإجباري عند التغيير — docs/12 بند ٤
        User::observe(UserPasswordHistoryObserver::class);
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
        // بداية ونهاية الانتحال — docs/12 بند ٣ قاعدة ٤
        Event::listen(EnterImpersonation::class, [LogImpersonationActivity::class, 'handleEnter']);
        Event::listen(LeaveImpersonation::class, [LogImpersonationActivity::class, 'handleLeave']);
    }
}
