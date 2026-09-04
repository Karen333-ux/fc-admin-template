<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Presentation\Filament\Pages;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Infrastructure\Validation\PasswordHistoryValidation;

/**
 * صفحة الملف الشخصي — نفس صفحة Filament الأصلية، زائد منع إعادة استخدام
 * كلمة مرور قديمة. (docs/12 بند ٤)
 *
 * ⚠️ الصفحة دي أصلاً تغيير ذاتي حقيقي لكلمة المرور — مفعّلة من Slice 4.4
 * (`->profile()`) عشان إدارة 2FA الذاتية. `UserResource` مش المسار
 * الوحيد لتغيير كلمة مرور، فقاعدة «منع إعادة الاستخدام» لازم تتفرض هنا
 * كمان مش بس في التغيير الإداري.
 *
 * تسجيل التاريخ والإشعار الإجباري بيحصلوا تلقائياً من
 * `UserPasswordHistoryObserver` (مستوى الموديل) — مفيش داعي لتكرارهم هنا.
 */
final class EditProfile extends BaseEditProfile
{
    protected function getPasswordFormComponent(): Component
    {
        $component = parent::getPasswordFormComponent();

        if (! $component instanceof TextInput) {
            return $component;
        }

        $user = $this->getUser();

        return $component->rule(fn () => PasswordHistoryValidation::rule($user instanceof User ? $user : null));
    }
}
