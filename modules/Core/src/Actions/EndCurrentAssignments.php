<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Modules\Core\Models\Driver;
use Modules\Core\Models\Vehicle;
use Modules\Core\Models\VehicleAssignment;

/**
 * Closes the current assignment of a vehicle or a driver that can no longer
 * be assigned: a vehicle retired or deleted, a person who leaves the
 * company or stops driving.
 */
final class EndCurrentAssignments
{
    public function __construct(
        private readonly EndVehicleAssignment $end,
    ) {}

    public function handle(Vehicle|Driver $holder): void
    {
        $column = $holder instanceof Vehicle ? 'vehicle_id' : 'driver_id';

        foreach (VehicleAssignment::query()->current()->where($column, $holder->getKey())->get() as $assignment) {
            $this->end->handle($assignment);
        }
    }
}
