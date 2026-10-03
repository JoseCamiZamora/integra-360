<?php

declare(strict_types=1);

namespace Modules\Core\Policies;

use App\Support\Tenancy\CompanyContext;
use Modules\Core\Models\User;
use Modules\Core\Models\VehicleAssignment;
use Modules\Core\Policies\Concerns\ChecksOperationalWriteAccess;

/**
 * Vehicle assignments of the active company. A driver may view their own.
 * Assignments are closed, never edited or deleted (they are the history).
 * Explicit rule for the platform administrator: no access.
 */
final class VehicleAssignmentPolicy
{
    use ChecksOperationalWriteAccess;

    public function viewAny(User $user): bool
    {
        return ! $user->isPlatformAdmin() && $user->checkPermissionTo('core.assignments.view');
    }

    public function view(User $user, VehicleAssignment $assignment): bool
    {
        if ($user->isPlatformAdmin() || ! $this->owns($assignment)) {
            return false;
        }

        return $assignment->driver->person->user_id === $user->getKey()
            || $user->checkPermissionTo('core.assignments.view');
    }

    public function create(User $user): bool
    {
        return ! $user->isPlatformAdmin() && $user->checkPermissionTo('core.assignments.create')
            && $this->canWriteOperationalData();
    }

    /**
     * Closing a current assignment.
     */
    public function end(User $user, VehicleAssignment $assignment): bool
    {
        return ! $user->isPlatformAdmin()
            && $this->owns($assignment)
            && $assignment->isCurrent()
            && $user->checkPermissionTo('core.assignments.update')
            && $this->canWriteOperationalData();
    }

    public function update(User $user, VehicleAssignment $assignment): bool
    {
        return false;
    }

    public function delete(User $user, VehicleAssignment $assignment): bool
    {
        return false;
    }

    private function owns(VehicleAssignment $assignment): bool
    {
        return CompanyContext::id() !== null && $assignment->company_id === CompanyContext::id();
    }
}
