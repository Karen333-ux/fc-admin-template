<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Application\Actions;

use function activity;

use Illuminate\Support\Facades\DB;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Domain\Models\UserDevice;

/**
 * بينهي جهاز واحد بعينه — زرار «إنهاء الجلسة» لكل صف في شاشة أجهزتي.
 * (docs/12 بند ٢)
 *
 * ⚠️ `ForceLogoutAction` بتاخد «كل حاجة ما عدا X» — مناسبة لزرار «إنهاء
 *    الكل»، مش لإنهاء جهاز واحد محدد. الشكل ده مش موصوف صراحةً في المثال
 *    البرمجي بـ docs/12، بس مطلوب من قايمة المتطلبات النصية (زرار لكل
 *    جهاز). تبنّي تقني محلي — مفيش تناقض معماري يستاهل ADR.
 */
final readonly class TerminateDeviceAction
{
    public function handle(User $user, UserDevice $device): void
    {
        abort_unless($device->user_id === $user->getKey(), 404);

        if ($device->session_id !== null) {
            DB::table(config('session.table', 'sessions'))
                ->where('id', $device->session_id)
                ->where('user_id', $user->getKey())
                ->delete();
        }

        $device->delete();

        activity('security')
            ->performedOn($user)
            ->event('device_terminated')
            ->withProperties([
                'device' => $device->device_name,
                'ip_address' => $device->ip_address,
            ])
            ->log(__('audit.events.user.device_terminated'));
    }
}
