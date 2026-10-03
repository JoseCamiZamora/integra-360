<?php

declare(strict_types=1);

namespace Modules\Core\Policies;

use App\Support\Tenancy\CompanyContext;
use Modules\Core\Models\Driver;
use Modules\Core\Models\User;

/**
 * Driver profiles of the active company (edited inside the person form;
 * see PersonPolicy::manageDriverProfile). A driver may view their own.
 * Explicit rule for the platform administrator: no access.
 */
final class DriverPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isPlatformAdmin() && $user->checkPermissionTo('core.drivers.view');
    }

    public function view(User $user, Driver $driver): bool
    {
        if ($user->isPlatformAdmin() || ! $this->owns($driver)) {
            return false;
        }

        return $driver->person->user_id === $user->getKey() || $user->checkPermissionTo('core.drivers.view');
    }

    public function create(User $user): bool
    {
        return ! $user->isPlatformAdmin() && $user->checkPermissionTo('core.drivers.update');
    }

    public function update(User $user, Driver $driver): bool
    {
        return ! $user->isPlatformAdmin() && $this->owns($driver) && $user->checkPermissionTo('core.drivers.update');
    }

    public function delete(User $user, Driver $driver): bool
    {
        return $this->update($user, $driver);
    }

    public function forceDelete(User $user, Driver $driver): bool
    {
        return false;
    }

    private function owns(Driver $driver): bool
    {
        return CompanyContext::id() !== null && $driver->company_id === CompanyContext::id();
    }
}
