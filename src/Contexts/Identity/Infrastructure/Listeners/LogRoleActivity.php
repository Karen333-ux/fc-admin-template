<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Infrastructure\Listeners;

use function activity;
use function auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Role;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

/**
 * بيسجّل تغييرات الأدوار على أي موديل — تصعيد صلاحيات، أعلى خطر أمني في
 * `CLAUDE.md`. (docs/11 بند ٥ · docs/20)
 *
 * ⚠️ مفيش شاشة إسناد أدوار في الواجهة لسه — لكن الحدث ده بيتطلق من
 *    `HasRoles::assignRole()/removeRole()` **نفسها**، مش من الواجهة. أي
 *    كود حالي أو مستقبلي (Action، Tinker، Seeder) بينادي الميثودز دي بيتسجّل
 *    تلقائياً — مفيش نقطة تكامل تانية أضمن من كده.
 *
 * ⚠️ `log_name = security` بالقصد — تصعيد الصلاحيات مايتحذفش أبداً بالتقليم
 *    الدوري (`config('activitylog.retention_months')`).
 *
 * ⚠️ `events_enabled` في `config/permission.php` لازم `true` — من غيرها
 *    الحدثين دول ماينطلقوش خالص (اتفحص السورس المُثبَّت).
 */
final class LogRoleActivity
{
    public function handleAttached(RoleAttachedEvent $event): void
    {
        $this->log($event->model, $event->rolesOrIds, 'role_attached');
    }

    public function handleDetached(RoleDetachedEvent $event): void
    {
        $this->log($event->model, $event->rolesOrIds, 'role_detached');
    }

    private function log(Model $model, mixed $rolesOrIds, string $eventKey): void
    {
        activity('security')
            ->performedOn($model)
            ->causedBy(auth()->user())
            ->event($eventKey)
            ->withProperties(['roles' => $this->normalizeRoles($rolesOrIds)])
            ->log(__("audit.events.user.{$eventKey}"));
    }

    /** @return list<int|string> */
    private function normalizeRoles(mixed $rolesOrIds): array
    {
        $items = match (true) {
            $rolesOrIds instanceof Collection => $rolesOrIds->all(),
            is_array($rolesOrIds) => $rolesOrIds,
            default => [$rolesOrIds],
        };

        return array_values(array_map(
            static fn (mixed $role): int|string => $role instanceof Role ? $role->getKey() : $role,
            $items,
        ));
    }
}
