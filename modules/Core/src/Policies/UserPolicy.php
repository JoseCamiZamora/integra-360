<?php

declare(strict_types=1);

namespace Modules\Core\Policies;

use App\Support\Tenancy\CompanyContext;
use Modules\Core\Models\Membership;
use Modules\Core\Models\User;

/**
 * Users of the active company: a user is "ours" only if they have a
 * membership in it (users are not company-scoped, decision 2).
 */
final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('core.users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $this->isMember($target) && $user->checkPermissionTo('core.users.view');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('core.users.create');
    }

    public function update(User $user, User $target): bool
    {
        return $this->isMember($target) && $user->checkPermissionTo('core.users.update');
    }

    /**
     * Nobody deactivates themselves (it would lock the last administrator out).
     */
    public function deactivate(User $user, User $target): bool
    {
        return $this->isMember($target)
            && ! $user->is($target)
            && $user->checkPermissionTo('core.users.deactivate');
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $this->isMember($target)
            && ! $user->is($target)
            && ! $target->isPlatformAdmin()
            && $user->checkPermissionTo('core.users.reset-password');
    }

    /**
     * Accounts are deactivated per company, never deleted.
     */
    public function delete(User $user, User $target): bool
    {
        return false;
    }

    private function isMember(User $target): bool
    {
        return CompanyContext::check()
            && Membership::query()->where('user_id', $target->getKey())->exists();
    }
}
