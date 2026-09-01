<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Authorization;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * سلسلة فحص تفويض. بتقف عند أول رفض وبترجّع سببه.
 * الترتيب مقصود: حد المستأجر، بعدين الصلاحية، بعدين قواعد الأعمال.
 */
final class Decision
{
    private ?Response $denial = null;

    /**
     * حارس المستأجر (ADR-005). بيتنادى تلقائياً من Policy::decideFor()
     * — متنادهاش بإيدك.
     *
     * 404 مش 403: «ممنوع» بتأكد إن السجل موجود، وده تسريب في حد ذاته.
     * الموديلات المش تابعة لمستأجر (Tenant, User — ADR-002) بتعدّي من غير فحص.
     */
    public function withinTenant(Model $record): self
    {
        if ($this->denial !== null || ! app(TenantBoundary::class)->crosses($record)) {
            return $this;
        }

        $this->denial = Response::denyAsNotFound(
            __('authorization.denied.record_not_found'),
        );

        return $this;
    }

    /** الفحص التاني دايماً: هل معاه الصلاحية أصلاً؟ */
    public function permission(
        Authenticatable $user,
        Policy $policy,
        string $action,
    ): self {
        if ($this->denial !== null) {
            return $this;
        }

        $permission = $policy->permissionFor($action);

        // config() مش filament(): الـ Policy بتتنادى من Job و Command و API
        // و Test — ومفيش لوحة Filament مبنية في الحالات دي. (ADR-006)
        try {
            $granted = $user->hasPermissionTo($permission, config('authorization.guard'));
        } catch (PermissionDoesNotExist) {
            // صلاحية موجودة في الكتالوج بس لسه ماتزامنتش (`authorization:sync`).
            // بنرفض — مانرميش. نفس سلوك الـ Gates في AuthorizationServiceProvider،
            // عشان المسارين مايختلفوش على نفس المدخل.
            $granted = false;
        }

        if (! $granted) {
            $this->denial = Response::deny(
                __('authorization.denied.missing_permission', [
                    'permission' => permission_label($permission),
                ]),
            );
        }

        return $this;
    }

    /**
     * قاعدة أعمال. الرسالة بتوصل للمستخدم فعلاً — خليها مفيدة.
     *
     * @param  array<string, string>  $replace
     */
    public function rule(bool $passes, string $messageKey, array $replace = []): self
    {
        if ($this->denial !== null || $passes) {
            return $this;
        }

        $this->denial = Response::deny(__("authorization.denied.{$messageKey}", $replace));

        return $this;
    }

    /**
     * نفس rule() بس بتقييم كسول — للفحوصات اللي فيها استعلام.
     *
     * @param  array<string, string>  $replace
     */
    public function ruleUsing(callable $passes, string $messageKey, array $replace = []): self
    {
        if ($this->denial !== null) {
            return $this;
        }

        return $this->rule((bool) $passes(), $messageKey, $replace);
    }

    /**
     * رفض بإخفاء وجود السجل (404 بدل 403).
     * استخدمها لما مجرد معرفة إن السجل موجود يعتبر تسريب.
     */
    public function ruleOrNotFound(bool $passes, string $messageKey): self
    {
        if ($this->denial !== null || $passes) {
            return $this;
        }

        $this->denial = Response::denyAsNotFound(__("authorization.denied.{$messageKey}"));

        return $this;
    }

    public function response(): Response
    {
        return $this->denial ?? Response::allow();
    }
}
