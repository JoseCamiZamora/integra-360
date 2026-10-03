<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Modules\Core\Enums\CompanyRole;
use Modules\Core\Models\Driver;
use Modules\Core\Models\Person;
use Modules\Core\Support\PersonInput;

/**
 * Creates, updates or removes the driver profile of a person.
 *
 * - Removing soft deletes it (assignments keep their history); adding it
 *   again restores the same profile.
 * - If the person has a user account, the "driver" role follows the profile
 *   in the active company (other roles are kept), so /conductor matches.
 */
final class SyncDriverProfile
{
    public function __construct(
        private readonly SyncUserRoles $syncRoles,
    ) {}

    /**
     * @param  array<string, mixed>|null  $data  null = the person does not drive
     */
    public function handle(Person $person, ?array $data): ?Driver
    {
        $driver = Driver::withTrashed()->where('person_id', $person->getKey())->first();

        if ($data === null) {
            if ($driver instanceof Driver && ! $driver->trashed()) {
                $driver->delete();
            }

            $this->syncDriverRole($person, false);
            $person->unsetRelation('driver');

            return null;
        }

        $validated = PersonInput::validateDriver($data);

        if ($driver instanceof Driver) {
            if ($driver->trashed()) {
                $driver->restore();
            }

            $driver->update($validated);
        } else {
            $driver = new Driver($validated);
            $driver->person()->associate($person);
            $driver->save();
        }

        $this->syncDriverRole($person, true);
        $person->setRelation('driver', $driver);

        return $driver;
    }

    private function syncDriverRole(Person $person, bool $drives): void
    {
        $user = $person->user;

        if ($user === null) {
            return;
        }

        $roles = $user->forgetCompanyRoles()->getRoleNames()->all();
        $wanted = $drives
            ? array_values(array_unique([...$roles, CompanyRole::Driver->value]))
            : array_values(array_diff($roles, [CompanyRole::Driver->value]));

        // Never leave an account without roles: it keeps "driver" until an
        // administrator gives it another role or deactivates the person.
        if ($wanted !== [] && $wanted !== $roles) {
            $this->syncRoles->handle($user, $wanted);
        }
    }
}
