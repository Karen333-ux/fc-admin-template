<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use pxlrbt\FilamentActivityLog\Pages\ListActivitiesBySubject;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource;
use Src\Support\Infrastructure\ActivityLog\ActivityLogContext;

/**
 * سجل نشاط مستخدم واحد — مش متصفّح عام عبر كل المستأجرين. (docs/11 بند ٨)
 *
 * ⚠️ `pxlrbt/filament-activity-log` v3.1.2 مابيشحنش متصفّح نشاط عام —
 *    اتفحص السورس. الصفحة المتاحة **لكل سجل على حدة** بس
 *    (`ListActivitiesBySubject`)، وده بالظبط اللي بيمنع الحاجة لـ Policy
 *    على `Activity` (اللي `TenantBoundary` مش قادرة تحميها أصلاً — شوف
 *    `ActivityLogContext`).
 *
 * حدّين للعزل هنا:
 *   ١) حل السجل نفسه بيعدّي على `UserResource::getEloquentQuery()` اللي
 *      مقيّدة بالمستأجر الحالي أصلاً — مستخدم مش عضو في مستأجرك، الرابط
 *      المباشر بيرجّع ٤٠٤. (`InteractsWithRecord::resolveRecord()`)
 *   ٢) المستخدم نفسه ممكن يكون عضو في أكتر من مستأجر — فسجل نشاطه ممكن
 *      يحمل أحداث مستأجرات تانية. `getActivities()` هنا بتفلتر عليها
 *      بـ `ActivityLogContext::scope()` صراحةً، **فوق** فلتر السجل.
 */
final class ListUserActivities extends ListActivitiesBySubject
{
    protected static string $resource = UserResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return Gate::allows('view.activity_logs');
    }

    /** @return Paginator<int, Activity>|CursorPaginator<int, Activity> */
    public function getActivities(): Paginator|CursorPaginator
    {
        $record = $this->getRecord();

        if (! $record instanceof User) {
            throw new RuntimeException('صفحة نشاط المستخدم لازم تشتغل على موديل User.');
        }

        $query = app(ActivityLogContext::class)->scope(
            $record->activitiesAsSubject()->with('causer')->latest()->getQuery(),
        );

        return $this->paginateQuery($query);
    }
}
