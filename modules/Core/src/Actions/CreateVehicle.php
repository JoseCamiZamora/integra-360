<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Enums\VehicleStatus;
use Modules\Core\Models\Vehicle;
use Modules\Core\Support\OperationalLimits;
use Modules\Core\Support\VehicleInput;

/**
 * Registers a vehicle of the active company, within the license limit of
 * vehicles in service.
 */
final class CreateVehicle
{
    public function __construct(
        private readonly OperationalLimits $limits,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(array $data): Vehicle
    {
        $validated = VehicleInput::validate($data);

        return DB::transaction(function () use ($validated): Vehicle {
            if (($validated['status'] ?? VehicleStatus::Active->value) !== VehicleStatus::Retired->value) {
                $this->limits->ensureCanAddVehicle();
            }

            return Vehicle::query()->create($validated);
        });
    }
}
