<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Infrastructure\Validation;

use Closure;
use Illuminate\Support\Facades\Hash;
use Src\Contexts\Identity\Domain\Models\User;

/**
 * منع إعادة استخدام آخر N كلمة مرور. (docs/12 بند ٤)
 *
 * ⚠️ نمط الإغلاق (closure) مطابق للاتفاقية الموجودة فعلاً في الفورمز
 *    المخصصة بالمشروع (`ManageAppearance`/`ManageStorage` — `->rule(static
 *    function (): Closure {...})`)، مش كلاس `Illuminate\Contracts\Validation\ValidationRule`
 *    جديد — الأول موجود ومستخدم أصلاً، التاني نمط موازي.
 *
 * ⚠️ الكلاس ده مشترك بين `UserResource` (تغيير إداري) و`EditProfile`
 *    المخصصة (تغيير ذاتي — Slice 4.4) عشان القاعدة تتفرض في المكانين
 *    الحقيقيين اللي كلمة المرور بتتغيّر منهم، مش مكان واحد بس.
 */
final class PasswordHistoryValidation
{
    /**
     * @return Closure(string, mixed, Closure): void
     */
    public static function rule(?User $target): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($target): void {
            // إنشاء — مفيش تاريخ نتحقق منه أصلاً (ADR-009: $record فاضي وقتها)
            if ($target === null || ! is_string($value)) {
                return;
            }

            if (Hash::check($value, $target->password)) {
                $fail(__('identity::identity.validation.password_recently_used'));

                return;
            }

            $count = (int) config('security.password.history_count', 5);

            /** @var list<string> $recentHashes */
            $recentHashes = $target->passwordHistories()
                ->latest()
                ->limit($count)
                ->pluck('password_hash')
                ->all();

            foreach ($recentHashes as $hash) {
                if (Hash::check($value, $hash)) {
                    $fail(__('identity::identity.validation.password_recently_used'));

                    return;
                }
            }
        };
    }
}
