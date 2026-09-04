<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Infrastructure\Policies;

use Illuminate\Auth\Access\Response;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Support\Infrastructure\Authorization\ImpersonationContext;
use Src\Support\Infrastructure\Authorization\Policy;

/**
 * الاختبار الحقيقي لـ docs/19 — بتغطي كل نمط. (docs/23 بند ٤)
 */
final class UserPolicy extends Policy
{
    /**
     * قواعد سلامة المدير العام مابيتخطاهاش (docs/19 بند ٥)
     *
     * ⚠️ `impersonate` مضافة عشان المدير العام نفسه يفضل محكوم بقواعد
     *    `impersonate()` تحت (مايقدرش ينتحل نفسه، ولا مدير عام تاني، ولا
     *    يبدأ انتحال جوّه انتحال) — مش بس تجاوز عام من Gate::before.
     *    (docs/12 بند ٣ قاعدة ٢)
     */
    public function invariants(): array
    {
        return ['delete', 'impersonate'];
    }

    public function viewAny(User $user): Response
    {
        return $this->decide()->permission($user, $this, 'view_any')->response();
    }

    public function create(User $user): Response
    {
        return $this->decide()->permission($user, $this, 'create')->response();
    }

    public function view(User $user, User $target): Response
    {
        return $this->decideFor($target)
            ->permission($user, $this, 'view')
            ->response();
    }

    public function update(User $user, User $target): Response
    {
        return $this->decideFor($target)
            ->permission($user, $this, 'update')
            ->rule(! $target->trashed(), 'record_trashed')
            ->response();
    }

    public function delete(User $user, User $target): Response
    {
        return $this->decideFor($target)
            ->permission($user, $this, 'delete')
            ->rule($user->isNot($target), 'self_target')   // ← قاعدة سلامة
            // عملية حساسة معطّلة أثناء الانتحال — docs/12 بند ٣ قاعدة ٦
            ->rule(! app(ImpersonationContext::class)->isActive(), 'blocked_while_impersonating')
            ->response();
    }

    /**
     * انتحال شخصية مستخدم — المسار الرسمي والوحيد للوصول لبيانات مستأجر
     * تاني (ADR-005 · docs/12 بند ٣).
     *
     * `decideFor()` لأن الدالة بتاخد سجل (ADR-007). `User` مش تابع لمستأجر
     * (ADR-002) فحارس المستأجر بيعدّي من غير فحص — بس الشكل بيفضل موحّد
     * والاختبار المعماري بيفرضه على كل دالة بتاخد سجل.
     */
    public function impersonate(User $user, User $target): Response
    {
        return $this->decideFor($target)
            ->permission($user, $this, 'impersonate')
            ->rule($user->isNot($target), 'self_target')
            ->rule(! $target->hasRole(config('authorization.super_admin_role')), 'cannot_impersonate_super_admin')
            ->rule(! app(ImpersonationContext::class)->isActive(), 'while_impersonating')
            ->response();
    }

    /**
     * تعيين/إعادة تعيين كلمة مرور مستخدم.
     *
     * صلاحية **منفصلة** عن `update` — الكتالوج بيعرّفها كـ `reset_password.users`
     * (`config/authorization.php` → `extra`). من غيرها، أي حد معاه `update.users`
     * كان يقدر يغيّر كلمة مرور زميل في نفس المستأجر ويدخل بحسابه — بما فيهم
     * مدير عام عضو في نفس المستأجر. ده تصعيد صلاحيات جوّه حدود المستأجر.
     *
     * `$target` اختياري عشان الشكل المعياري في ADR-009: على صفحة الإنشاء
     * القدرة بتتسأل بالكلاس (`$record ?? User::class`) ومفيش سجل، ولارافيل
     * بيشيل الـ class-string من المعاملات.
     */
    public function resetPassword(User $user, ?User $target = null): Response
    {
        $decision = $target === null
            ? $this->decide()                 // صفحة الإنشاء — مفيش سجل
            : $this->decideFor($target);      // حارس المستأجر (ADR-007)

        return $decision
            ->permission($user, $this, 'reset_password')
            // عملية حساسة معطّلة أثناء الانتحال — docs/12 بند ٣ قاعدة ٦
            ->rule(! app(ImpersonationContext::class)->isActive(), 'blocked_while_impersonating')
            ->response();
    }

    protected function resource(): string
    {
        return 'users';
    }
}
