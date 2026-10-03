<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Vehicle;

/**
 * Soft deletes a vehicle (it is never deleted physically) and closes its
 * current assignment. Its documents and history are kept.
 */
final class DeleteVehicle
{
    public function __construct(
        private readonly EndCurrentAssignments $endAssignments,
    ) {}

    public function handle(Vehicle $vehicle): void
    {
        DB::transaction(function () use ($vehicle): void {
            $this->endAssignments->handle($vehicle);
            $vehicle->delete();
        });
    }
}
