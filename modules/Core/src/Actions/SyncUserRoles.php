<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use App\Support\Tenancy\CompanyContext;
use Modules\Core\Models\User;

/**
 * Sets the user's roles in the active company. Only the differences are
 * attached or detached, so the audit log records real changes.
 */
final class SyncUserRoles
{
    /**
     * @param  list<string>  $roles
     */
    public function handle(User $user, array $roles): void
    {
        CompanyContext::requireId(User::class);

        $user->forgetCompanyRoles();
        $current = $user->getRoleNames()->all();

        $toRemove = array_values(array_diff($current, $roles));
        $toAdd = array_values(array_diff($roles, $current));

        foreach ($toRemove as $role) {
            $user->removeRole($role);
        }

        if ($toAdd !== []) {
            $user->assignRole($toAdd);
        }

        $user->forgetCompanyRoles();
    }
}
