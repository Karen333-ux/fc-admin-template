<?php

declare(strict_types=1);

namespace Src\Support\Presentation\Filament\Notifications;

use Filament\Notifications\Livewire\DatabaseNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\DatabaseNotification;
use Src\Support\Infrastructure\Notifications\NotificationOwnership;

/**
 * جرس الإشعارات، مقيّد على المستأجر الحالي. (ADR-024)
 *
 * ⚠️ الأصل في Filament بيرجّع `$user->notifications()` من غير أي تقييد
 *    (`vendor/filament/notifications/src/Livewire/DatabaseNotifications.php`
 *    سطر ١٠٣-١١٣). يعني مستخدم عضو في مؤسستين كان هيشوف إشعارات
 *    المؤسستين في الاتنين — وجسم الإشعار ممكن يكشف وجود سجل في مؤسسة
 *    السياق الحالي مالوش علاقة بيها.
 *
 * بنمدّ الدالة الوحيدة اللي بتبني الاستعلام، فكل حاجة مبنية فوقها (العدّ،
 * «علّم الكل كمقروء»، المسح، الصفحات) بتورث التقييد تلقائياً بدل ما نكرّره.
 */
class TenantAwareDatabaseNotifications extends DatabaseNotifications
{
    /**
     * @return Builder<DatabaseNotification>|Relation<DatabaseNotification, Model, Collection<int, DatabaseNotification>>
     */
    public function getNotificationsQuery(): Builder|Relation
    {
        return app(NotificationOwnership::class)->scope(parent::getNotificationsQuery());
    }
}
