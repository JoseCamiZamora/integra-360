<?php

declare(strict_types=1);

namespace Modules\Core\Policies;

use App\Support\Tenancy\CompanyContext;
use Modules\Core\Models\Branch;
use Modules\Core\Models\User;

/**
 * Branches of the active company. The record check repeats the company scope
 * on purpose: a policy must hold even for a model loaded some other way.
 */
final class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('core.branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->owns($branch) && $user->checkPermissionTo('core.branches.view');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('core.branches.create');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->owns($branch) && $user->checkPermissionTo('core.branches.update');
    }

    /**
     * The main branch cannot be deleted: mark another one as main first.
     */
    public function delete(User $user, Branch $branch): bool
    {
        return $this->owns($branch) && ! $branch->is_main && $user->checkPermissionTo('core.branches.delete');
    }

    private function owns(Branch $branch): bool
    {
        return CompanyContext::id() !== null && $branch->company_id === CompanyContext::id();
    }
}
