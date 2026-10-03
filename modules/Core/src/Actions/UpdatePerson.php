<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Person;
use Modules\Core\Support\PersonInput;

/**
 * Updates a person's data and driver profile. The status (active or
 * inactive) is changed with SetPersonStatus.
 */
final class UpdatePerson
{
    public function __construct(
        private readonly SyncDriverProfile $driverProfile,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $driver  null = does not drive (removes the profile)
     *
     * @throws ValidationException
     */
    public function handle(Person $person, array $data, ?array $driver): Person
    {
        $validated = PersonInput::validate($data, $person->getKey());

        if ($driver !== null) {
            PersonInput::validateDriver($driver);
        }

        return DB::transaction(function () use ($person, $validated, $driver): Person {
            $person->update($validated);
            $this->driverProfile->handle($person, $driver);

            return $person;
        });
    }
}
