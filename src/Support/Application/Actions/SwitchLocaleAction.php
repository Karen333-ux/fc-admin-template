<?php

declare(strict_types=1);

namespace Src\Support\Application\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Src\Support\Domain\Exceptions\UnsupportedLocaleException;

/**
 * بيبدّل لغة المستخدم. (docs/10 بند ٤)
 *
 * بيكتب في الاتنين بالقصد:
 *   • `users.locale` — عشان الإشعارات والطوابير تعرف اللغة. الجلسة
 *     مابتوصلش هناك (`Queue` مالوش سيشن)، فالاعتماد عليها لوحدها معناه
 *     إن رسايل البريد بتطلع بلغة غلط.
 *   • الجلسة — عشان الطلب الجاي يشوف التغيير فوراً حتى لو المستخدم ضيف.
 *
 * ⚠️ اللغة **بتتحقّق من `supported_locales`** قبل أي كتابة. من غير ده
 *    أي قيمة من الطلب بتتخزّن في قاعدة البيانات وبتتحط في `setLocale()`.
 */
final readonly class SwitchLocaleAction
{
    /** @throws UnsupportedLocaleException */
    public function handle(string $locale): void
    {
        /** @var list<string> $supported */
        $supported = config('app.supported_locales', []);

        if (! in_array($locale, $supported, true)) {
            throw UnsupportedLocaleException::for($locale, $supported);
        }

        Auth::user()?->forceFill(['locale' => $locale])->save();

        Session::put('locale', $locale);
    }
}
