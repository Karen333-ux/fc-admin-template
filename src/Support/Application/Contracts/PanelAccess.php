<?php

declare(strict_types=1);

namespace Src\Support\Application\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * الواجهة في Application والتنفيذ في Infrastructure — عشان موديل الـ Domain
 * يعتمد على تجريد بدل كلاس بيلمس PermissionRegistrar. (ADR-012 · docs/22 بند ٤)
 */
interface PanelAccess
{
    /**
     * هل المستخدم عنده القدرة دي في **أي** مستأجر من مستأجريه؟
     *
     * الدخول للوحة بيتفحص قبل ما يتحدد مستأجر، فسؤال «في المستأجر الحالي؟»
     * مالوش إجابة وقتها. (ADR-026)
     *
     * @param  list<int>  $tenantIds
     */
    public function allowsInAnyTenant(Authenticatable $user, string $ability, array $tenantIds): bool;
}
