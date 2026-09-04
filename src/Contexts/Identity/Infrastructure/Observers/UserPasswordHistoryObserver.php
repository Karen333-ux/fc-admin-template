<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Infrastructure\Observers;

use Src\Contexts\Identity\Domain\Models\PasswordHistory;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Infrastructure\Notifications\PasswordChangedNotification;

/**
 * بيسجّل كلمة المرور القديمة في التاريخ وبيبعت إشعار تغيير. (docs/12 بند ٤)
 *
 * ⚠️ على مستوى الموديل مش الفورم — بيغطّي **أي** مسار كلمة مرور بتتغيّر
 *    منه: `UserResource` (إداري) و`EditProfile` المخصصة (ذاتي)، من غير
 *    ما نكرّر منطق التسجيل/الإشعار في المكانين. لو مسار تالت اتضاف بعدين
 *    (API مثلاً)، بيتغطّى تلقائياً برضه.
 */
final class UserPasswordHistoryObserver
{
    /**
     * قبل الحفظ — بنسجّل الهاش **القديم** (اللي هيتستبدل) في التاريخ.
     * `getOriginal()` بيرجّع القيمة زي ما هي في قاعدة البيانات، مش القيمة
     * الجديدة المعلّقة.
     */
    public function saving(User $user): void
    {
        if (! $user->exists || ! $user->isDirty('password')) {
            return;
        }

        $originalHash = $user->getOriginal('password');

        if (! is_string($originalHash) || $originalHash === '') {
            return;
        }

        PasswordHistory::query()->create([
            'user_id' => $user->getKey(),
            'password_hash' => $originalHash,
        ]);

        $this->pruneHistory($user);
    }

    /**
     * بعد الحفظ — إشعار إجباري، بس لو ده تغيير فعلي مش إنشاء أول حساب.
     */
    public function saved(User $user): void
    {
        if ($user->wasRecentlyCreated || ! $user->wasChanged('password')) {
            return;
        }

        $user->notify(new PasswordChangedNotification);
    }

    /**
     * الاحتفاظ بآخر N صف بس — القديم بيتحذف. (docs/12 بند ٤: «آخر ٥»)
     */
    private function pruneHistory(User $user): void
    {
        $keep = (int) config('security.password.history_count', 5);

        $staleIds = $user->passwordHistories()
            ->orderByDesc('id')
            ->skip($keep)
            ->take(PHP_INT_MAX)
            ->pluck('id');

        if ($staleIds->isNotEmpty()) {
            PasswordHistory::query()->whereIn('id', $staleIds)->delete();
        }
    }
}
