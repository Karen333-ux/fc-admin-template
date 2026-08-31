<?php

declare(strict_types=1);

namespace Src\Support\Application\Contracts;

/**
 * الواجهة في Application والتنفيذ في Infrastructure — عشان BelongsToTenant
 * يعتمد على تجريد، مش على كلاس فيه Filament::getTenant(). (docs/22 بند ٤)
 */
interface TenantContext
{
    /** المستأجر الحالي، أو null لو مفيش سياق */
    public function id(): ?int;

    /** يضبط السياق صراحةً — بيقبل null كقيمة مقصودة */
    public function set(?int $tenantId): void;

    /** يرجّع لسلوك «خد المستأجر من Filament» (ADR-008) */
    public function forget(): void;

    /** هل إحنا جوه withoutScope حالياً؟ TenantScope بيسأل عليها */
    public function isBypassed(): bool;

    /** قراءة عبر كل المستأجرين — بشروط docs/20 بند ٣-٧ */
    public function withoutScope(callable $callback): mixed;

    public function forEachTenant(callable $callback): void;
}
