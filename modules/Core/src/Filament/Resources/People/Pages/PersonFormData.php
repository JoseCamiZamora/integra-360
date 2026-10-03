<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\People\Pages;

use Modules\Core\Models\Person;
use Modules\Core\Support\PersonInput;

/**
 * Splits the person form (person + driver profile fields) into what the
 * actions receive.
 */
final class PersonFormData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function person(array $data): array
    {
        return array_intersect_key($data, PersonInput::rules(null));
    }

    /**
     * The driver profile data, null if the person does not drive. Without
     * the right to change the profile (disabled fields are not sent), the
     * current profile is kept as it is.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public static function driver(array $data, ?Person $person = null): ?array
    {
        $canManage = $person === null
            ? (auth()->user()?->checkPermissionTo('core.drivers.update') ?? false)
            : (auth()->user()?->can('manageDriverProfile', $person) ?? false);

        if (! $canManage) {
            return $person?->driver === null ? null : self::fill($person)['driver'];
        }

        return empty($data['is_driver']) ? null : array_intersect_key($data, PersonInput::driverRules());
    }

    /**
     * Form state of an existing person, including their driver profile.
     *
     * @return array{is_driver: bool, driver: array<string, mixed>|null, license_number?: string, license_category?: string, experience_years?: int|null}
     */
    public static function fill(Person $person): array
    {
        $driver = $person->driver;

        if ($driver === null) {
            return ['is_driver' => false, 'driver' => null];
        }

        $profile = [
            'license_number' => $driver->license_number,
            'license_category' => $driver->license_category->value,
            'experience_years' => $driver->experience_years,
        ];

        return ['is_driver' => true, 'driver' => $profile, ...$profile];
    }

    /**
     * @return list<string>
     */
    public static function fields(): array
    {
        return [...array_keys(PersonInput::rules(null)), ...array_keys(PersonInput::driverRules())];
    }
}
