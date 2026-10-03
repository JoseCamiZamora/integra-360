<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Vehicle;
use Modules\Core\Support\VehicleInput;

/**
 * Updates a vehicle of the active company, including its status. Retiring
 * a vehicle keeps it and its history, and closes its current assignment
 * (a retired vehicle cannot be assigned).
 */
final class UpdateVehicle
{
    public function __construct(
        private readonly EndCurrentAssignments $endAssignments,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(Vehicle $vehicle, array $data): Vehicle
    {
        $validated = VehicleInput::validate($data, $vehicle->getKey());

        return DB::transaction(function () use ($vehicle, $validated): Vehicle {
            $vehicle->update($validated);

            if ($vehicle->isRetired()) {
                $this->endAssignments->handle($vehicle);
            }

            return $vehicle;
        });
    }
}
