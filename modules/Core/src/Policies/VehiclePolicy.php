<?php

declare(strict_types=1);

namespace Modules\Core\Policies;

use App\Support\Tenancy\CompanyContext;
use Modules\Core\Models\User;
use Modules\Core\Models\Vehicle;

/**
 * Vehicles of the active company. Never deleted physically: "delete" is a
 * soft delete with its own permission; retiring is a status change.
 *
 * Explicit rule for the platform administrator: no access (operational data
 * of a company, where they have no role).
 */
final class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isPlatformAdmin() && $user->checkPermissionTo('core.vehicles.view');
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return ! $user->isPlatformAdmin() && $this->owns($vehicle) && $user->checkPermissionTo('core.vehicles.view');
    }

    public function create(User $user): bool
    {
        return ! $user->isPlatformAdmin() && $user->checkPermissionTo('core.vehicles.create');
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return ! $user->isPlatformAdmin()
            && $this->owns($vehicle)
            && ! $vehicle->trashed()
            && $user->checkPermissionTo('core.vehicles.update');
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return ! $user->isPlatformAdmin() && $this->owns($vehicle) && $user->checkPermissionTo('core.vehicles.delete');
    }

    public function restore(User $user, Vehicle $vehicle): bool
    {
        return $this->delete($user, $vehicle);
    }

    public function forceDelete(User $user, Vehicle $vehicle): bool
    {
        return false;
    }

    private function owns(Vehicle $vehicle): bool
    {
        return CompanyContext::id() !== null && $vehicle->company_id === CompanyContext::id();
    }
}
