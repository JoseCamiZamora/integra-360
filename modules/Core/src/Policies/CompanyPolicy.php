<?php

declare(strict_types=1);

namespace Modules\Core\Policies;

use App\Support\Tenancy\CompanyContext;
use Modules\Core\Models\Company;
use Modules\Core\Models\User;

/**
 * Platform administrator: lists, creates and (de)activates companies.
 * Company administrator: views and edits only the active company ("Mi
 * empresa"), never another one.
 */
final class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function view(User $user, Company $company): bool
    {
        return $user->isPlatformAdmin()
            || ($this->isActiveCompany($company) && $user->checkPermissionTo('core.company.view'));
    }

    public function create(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function update(User $user, Company $company): bool
    {
        return $user->isPlatformAdmin()
            || ($this->isActiveCompany($company) && $user->checkPermissionTo('core.company.update'));
    }

    /**
     * Companies are deactivated, never deleted (legal data retention).
     */
    public function delete(User $user, Company $company): bool
    {
        return false;
    }

    public function manageLicenses(User $user, Company $company): bool
    {
        return $user->isPlatformAdmin();
    }

    private function isActiveCompany(Company $company): bool
    {
        return CompanyContext::id() === $company->getKey();
    }
}
