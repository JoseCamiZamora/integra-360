<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Vehicle;
use Modules\Core\Support\VehicleInput;

/**
 * Registers a vehicle of the active company.
 */
final class CreateVehicle
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(array $data): Vehicle
    {
        return Vehicle::query()->create(VehicleInput::validate($data));
    }
}
