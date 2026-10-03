<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Vehicle;
use Modules\Core\Rules\UniquePlate;
use Modules\Core\Support\OperationalLimits;

/**
 * Restores a deleted vehicle, within the license limit (unless it is
 * retired) and only if its plate was not taken in the meantime.
 */
final class RestoreVehicle
{
    public function __construct(
        private readonly OperationalLimits $limits,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(Vehicle $vehicle): Vehicle
    {
        return DB::transaction(function () use ($vehicle): Vehicle {
            if (! $vehicle->isRetired()) {
                $this->limits->ensureCanAddVehicle();
            }

            $validator = Validator::make(['plate' => $vehicle->plate], ['plate' => [new UniquePlate($vehicle->getKey())]]);
            $validator->validate();

            $vehicle->restore();

            return $vehicle;
        });
    }
}
