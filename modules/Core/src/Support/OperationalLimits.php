<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use App\Support\Tenancy\CompanyContext;
use Illuminate\Validation\ValidationException;
use Modules\Core\Licensing\ModuleAccess;
use Modules\Core\Models\Company;
use Modules\Core\Models\Person;
use Modules\Core\Models\Vehicle;

/**
 * License limits for Core entities (ModuleAccess::effectiveLimits()). They
 * only block adding: creating, restoring or reactivating a vehicle or a
 * person. Editing, retiring and reading always work, and nothing is hidden
 * or deleted when a limit goes down.
 *
 * Counted: vehicles not retired nor deleted; active people.
 *
 * Call inside a transaction: the company row is locked so two simultaneous
 * additions cannot both pass the check.
 */
final class OperationalLimits
{
    public function __construct(
        private readonly ModuleAccess $access,
    ) {}

    /**
     * @throws ValidationException
     */
    public function ensureCanAddVehicle(): void
    {
        $limit = $this->access->effectiveLimits($this->lockCompany())->maxVehicles;

        if ($limit !== null && Vehicle::query()->inService()->count() >= $limit) {
            throw ValidationException::withMessages([
                'limit' => __('core::licenses.limits.vehicles_reached', ['max' => $limit]),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function ensureCanAddPerson(): void
    {
        $limit = $this->access->effectiveLimits($this->lockCompany())->maxPeople;

        if ($limit !== null && Person::query()->active()->count() >= $limit) {
            throw ValidationException::withMessages([
                'limit' => __('core::licenses.limits.people_reached', ['max' => $limit]),
            ]);
        }
    }

    private function lockCompany(): Company
    {
        return Company::query()->lockForUpdate()->findOrFail(CompanyContext::requireId(Company::class));
    }
}
