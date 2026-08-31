<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Infrastructure\Policies;

use Illuminate\Auth\Access\Response;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Support\Infrastructure\Authorization\Policy;

/**
 * الاختبار الحقيقي لـ docs/19 — بتغطي كل نمط. (docs/23 بند ٤)
 */
final class UserPolicy extends Policy
{
    /** قواعد سلامة المدير العام مابيتخطاهاش (docs/19 بند ٥) */
    public function invariants(): array
    {
        return ['delete'];
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
            ->response();
    }

    protected function resource(): string
    {
        return 'users';
    }
}
