<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\Core\Models\VehicleAssignment;

/**
 * Closes a current assignment (ends_at). Recorded in the audit log as an
 * update of ends_at. Closing an already closed assignment does nothing.
 */
final class EndVehicleAssignment
{
    public function handle(VehicleAssignment $assignment, ?CarbonInterface $endsAt = null): VehicleAssignment
    {
        if (! $assignment->isCurrent()) {
            return $assignment;
        }

        $endsAt ??= now();

        $assignment->ends_at = $endsAt->lessThan($assignment->starts_at) ? $assignment->starts_at : Carbon::instance($endsAt);
        $assignment->save();

        return $assignment;
    }
}
