<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Modules\Core\Models\VehicleAssignment;

/**
 * Outcome of AssignVehicle: the current assignment, the ones it closed and
 * the license warning to show (if any).
 */
final readonly class AssignmentResult
{
    /**
     * @param  list<VehicleAssignment>  $closed
     */
    public function __construct(
        public VehicleAssignment $assignment,
        public array $closed,
        public ?string $licenseWarning,
    ) {}
}
