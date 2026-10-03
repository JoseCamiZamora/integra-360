<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Modules\Core\Models\Driver;
use Modules\Core\Models\Vehicle;
use Modules\Core\Models\VehicleAssignment;
use Modules\Core\Models\VehicleTypeLicenseCategory;

/**
 * What assigning $driver to $vehicle would do, for the confirmation screen
 * and for AssignVehicle: the current assignments it closes and the license
 * warning (a warning, never a block).
 */
final readonly class AssignmentCheck
{
    public function __construct(
        public ?VehicleAssignment $vehicleCurrent,
        public ?VehicleAssignment $driverCurrent,
        public ?string $licenseWarning,
    ) {}

    public static function for(Vehicle $vehicle, Driver $driver): self
    {
        $vehicleCurrent = VehicleAssignment::query()->current()->where('vehicle_id', $vehicle->getKey())->with('driver.person')->first();
        $driverCurrent = VehicleAssignment::query()->current()->where('driver_id', $driver->getKey())->with('vehicle')->first();

        return new self(
            $vehicleCurrent?->driver_id === $driver->getKey() ? null : $vehicleCurrent,
            $driverCurrent?->vehicle_id === $vehicle->getKey() ? null : $driverCurrent,
            self::licenseWarning($vehicle, $driver),
        );
    }

    public static function licenseWarning(Vehicle $vehicle, Driver $driver): ?string
    {
        if (VehicleTypeLicenseCategory::allows($vehicle->vehicle_type, $driver->license_category)) {
            return null;
        }

        $allowed = array_map(fn ($category): string => $category->value, VehicleTypeLicenseCategory::allowedFor($vehicle->vehicle_type));

        return __('core::assignments.warnings.license_category', [
            'category' => $driver->license_category->value,
            'type' => $vehicle->vehicle_type->label(),
            'allowed' => $allowed === [] ? '—' : implode(', ', $allowed),
        ]);
    }

    /**
     * Human summary of the assignments that will be closed (confirmation).
     *
     * @return list<string>
     */
    public function closingSummary(): array
    {
        $lines = [];

        if ($this->vehicleCurrent !== null) {
            $lines[] = __('core::assignments.confirm.vehicle_has_driver', ['driver' => $this->vehicleCurrent->driver->person->full_name]);
        }

        if ($this->driverCurrent !== null) {
            $lines[] = __('core::assignments.confirm.driver_has_vehicle', ['plate' => $this->driverCurrent->vehicle->plate]);
        }

        return $lines;
    }
}
