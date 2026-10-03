<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Enums\PersonStatus;
use Modules\Core\Models\Membership;
use Modules\Core\Models\Person;
use Modules\Core\Support\OperationalLimits;

/**
 * Marks a person as active or inactive (left the company). Nothing is
 * deleted. If the person has a user account, their membership in the active
 * company follows: deactivated with the person and reactivated with them
 * (reversible). Both changes go to the audit log; access to other companies
 * is untouched. Retiring a driver also closes their current assignment;
 * reactivating respects the license limit of active people.
 */
final class SetPersonStatus
{
    public function __construct(
        private readonly EndCurrentAssignments $endAssignments,
        private readonly OperationalLimits $limits,
    ) {}

    public function handle(Person $person, PersonStatus $status): Person
    {
        if ($person->status === $status) {
            return $person;
        }

        return DB::transaction(function () use ($person, $status): Person {
            if ($status === PersonStatus::Active) {
                $this->limits->ensureCanAddPerson();
            }

            $person->status = $status;
            $person->save();

            if ($person->user_id !== null) {
                $membership = Membership::query()->where('user_id', $person->user_id)->first();

                if ($membership instanceof Membership) {
                    $membership->is_active = $status === PersonStatus::Active;
                    $membership->save();
                }
            }

            // Someone who left drives no vehicle; reactivating does not
            // restore it (the assignment is decided again).
            if ($status === PersonStatus::Inactive && $person->driver !== null) {
                $this->endAssignments->handle($person->driver);
            }

            return $person;
        });
    }
}
