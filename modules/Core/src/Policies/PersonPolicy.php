<?php

declare(strict_types=1);

namespace Modules\Core\Policies;

use App\Support\Tenancy\CompanyContext;
use Modules\Core\Models\Person;
use Modules\Core\Models\User;
use Modules\Core\Policies\Concerns\ChecksOperationalWriteAccess;

/**
 * People of the active company. A person may always view their own record
 * (drivers, from /conductor). Never deleted physically.
 *
 * Explicit rule for the platform administrator: no access (operational data
 * of a company, where they have no role).
 */
final class PersonPolicy
{
    use ChecksOperationalWriteAccess;

    public function viewAny(User $user): bool
    {
        return ! $user->isPlatformAdmin() && $user->checkPermissionTo('core.people.view');
    }

    public function view(User $user, Person $person): bool
    {
        if ($user->isPlatformAdmin() || ! $this->owns($person)) {
            return false;
        }

        return $person->user_id === $user->getKey() || $user->checkPermissionTo('core.people.view');
    }

    public function create(User $user): bool
    {
        return ! $user->isPlatformAdmin() && $user->checkPermissionTo('core.people.create')
            && $this->canWriteOperationalData();
    }

    public function update(User $user, Person $person): bool
    {
        return ! $user->isPlatformAdmin()
            && $this->owns($person)
            && ! $person->trashed()
            && $user->checkPermissionTo('core.people.update')
            && $this->canWriteOperationalData();
    }

    /**
     * Retiring or reactivating. With a user account it also (de)activates
     * their membership, so it needs core.users.deactivate too.
     */
    public function changeStatus(User $user, Person $person): bool
    {
        return $this->update($user, $person)
            && ($person->user_id === null || $user->checkPermissionTo('core.users.deactivate'))
            && $person->user_id !== $user->getKey();
    }

    /**
     * "Crear acceso al sistema": user management permission.
     */
    public function createAccess(User $user, Person $person): bool
    {
        return ! $user->isPlatformAdmin()
            && $this->owns($person)
            && $person->user_id === null
            && $person->isActive()
            && ! $person->trashed()
            && $user->checkPermissionTo('core.users.create')
            && $this->canWriteOperationalData();
    }

    /**
     * The driver profile section of the person form.
     */
    public function manageDriverProfile(User $user, Person $person): bool
    {
        return $this->update($user, $person) && $user->checkPermissionTo('core.drivers.update')
            && $this->canWriteOperationalData();
    }

    public function delete(User $user, Person $person): bool
    {
        return ! $user->isPlatformAdmin() && $this->owns($person) && $user->checkPermissionTo('core.people.delete')
            && $this->canWriteOperationalData();
    }

    public function restore(User $user, Person $person): bool
    {
        return $this->delete($user, $person);
    }

    public function forceDelete(User $user, Person $person): bool
    {
        return false;
    }

    private function owns(Person $person): bool
    {
        return CompanyContext::id() !== null && $person->company_id === CompanyContext::id();
    }
}
