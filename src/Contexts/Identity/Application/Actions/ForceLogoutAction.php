<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Application\Actions;

use function activity;

use Illuminate\Support\Facades\DB;
use Src\Contexts\Identity\Domain\Models\User;

/**
 * بينهي كل جلسات المستخدم ما عدا واحدة (اختيارية) — «إنهاء كل الجلسات
 * الأخرى». (docs/12 بند ٢)
 *
 * ⚠️ محتاج `SESSION_DRIVER=database` عشان نقدر نمسح جلسات محددة —
 *    الافتراضي في المشروع أصلاً (`.env.example` · CLAUDE.md).
 */
final readonly class ForceLogoutAction
{
    public function handle(User $user, ?string $exceptSessionId = null): int
    {
        $query = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey());

        if ($exceptSessionId !== null) {
            $query->where('id', '!=', $exceptSessionId);
        }

        $count = $query->delete();

        $devices = $user->devices();

        if ($exceptSessionId !== null) {
            // ⚠️ `session_id` عمود nullable — SQL بتاعت `!=` مابتطابقش صفوف
            //    NULL أبداً (منطق ثلاثي القيم)، يعني جهاز بـ session_id فاضي
            //    كان هيفضل من غير ما يتمسح أبداً. `whereNull` بتغطّي الحالة دي.
            $devices->where(function ($query) use ($exceptSessionId): void {
                $query->whereNull('session_id')
                    ->orWhere('session_id', '!=', $exceptSessionId);
            });
        }

        $devices->delete();

        activity('security')
            ->performedOn($user)
            ->event('force_logout')
            ->withProperties(['sessions_terminated' => $count])
            ->log(__('audit.events.user.force_logout'));

        return $count;
    }
}
