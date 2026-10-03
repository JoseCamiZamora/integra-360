<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Person;
use Modules\Core\Support\PersonInput;

/**
 * Registers a person of the active company, with their driver profile if
 * they drive. People start active and without a user account.
 */
final class CreatePerson
{
    public function __construct(
        private readonly SyncDriverProfile $driverProfile,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $driver  null = does not drive
     *
     * @throws ValidationException
     */
    public function handle(array $data, ?array $driver = null): Person
    {
        $validated = PersonInput::validate($data);

        if ($driver !== null) {
            PersonInput::validateDriver($driver);
        }

        return DB::transaction(function () use ($validated, $driver): Person {
            $person = Person::query()->create($validated);

            if ($driver !== null) {
                $this->driverProfile->handle($person, $driver);
            }

            return $person;
        });
    }
}
