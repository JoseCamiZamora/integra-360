<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Vehicle;
use Modules\Core\Support\VehicleInput;

/**
 * Updates a vehicle of the active company (including its status: retiring
 * a vehicle keeps it and its history).
 */
final class UpdateVehicle
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(Vehicle $vehicle, array $data): Vehicle
    {
        $vehicle->update(VehicleInput::validate($data, $vehicle->getKey()));

        return $vehicle;
    }
}
