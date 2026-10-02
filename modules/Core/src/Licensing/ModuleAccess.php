<?php

declare(strict_types=1);

namespace Modules\Core\Licensing;

use Illuminate\Support\Collection;
use Modules\Core\Enums\ModuleAccessLevel;
use Modules\Core\Models\Company;

/**
 * What each company may do with each module, according to its licenses.
 * Licensable modules use it through LicensedModulePolicy, the route
 * middleware and their scheduled tasks.
 */
interface ModuleAccess
{
    public function level(Company $company, string $moduleCode): ModuleAccessLevel;

    /**
     * The company can at least read the module (active license, or expired
     * less than 30 days ago): its navigation and routes are available.
     */
    public function isEnabled(Company $company, string $moduleCode): bool;

    /**
     * Active license within its dates: the company may create and change data.
     */
    public function canWrite(Company $company, string $moduleCode): bool;

    public function limits(Company $company, string $moduleCode): ModuleLimits;

    /**
     * Level for the active company (CompanyContext); None without one.
     */
    public function currentLevel(string $moduleCode): ModuleAccessLevel;

    /**
     * Companies whose scheduled tasks for the module must run: active
     * companies with write access.
     *
     * @return Collection<int, Company>
     */
    public function companiesWithWriteAccess(string $moduleCode): Collection;
}
