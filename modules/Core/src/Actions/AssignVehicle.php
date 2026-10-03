<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use App\Support\Tenancy\CompanyContext;
use App\Support\Tenancy\MissingCompanyContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Driver;
use Modules\Core\Models\Vehicle;
use Modules\Core\Models\VehicleAssignment;
use Modules\Core\Support\AssignmentCheck;
use Modules\Core\Support\AssignmentResult;

/**
 * Assigns a vehicle to a driver. The vehicle's current driver and the
 * driver's current vehicle are closed first (the screen asks for
 * confirmation), so each keeps at most one current assignment; the history
 * is kept. A license category not listed for the vehicle type is a warning,
 * never a block. Every assignment and closing goes to the audit log.
 */
final class AssignVehicle
{
    public function __construct(
        private readonly EndVehicleAssignment $end,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(Vehicle $vehicle, Driver $driver, ?CarbonInterface $startsAt = null, ?string $notes = null): AssignmentResult
    {
        $this->ensureAssignable($vehicle, $driver);

        $startsAt ??= now();

        return DB::transaction(function () use ($vehicle, $driver, $startsAt, $notes): AssignmentResult {
            // Lock both rows: two administrators assigning at once must not
            // both pass the checks.
            Vehicle::query()->whereKey($vehicle->getKey())->lockForUpdate()->first();
            Driver::query()->whereKey($driver->getKey())->lockForUpdate()->first();

            $check = AssignmentCheck::for($vehicle, $driver);

            $already = VehicleAssignment::query()->current()
                ->where('vehicle_id', $vehicle->getKey())
                ->where('driver_id', $driver->getKey())
                ->first();

            if ($already instanceof VehicleAssignment) {
                return new AssignmentResult($already, [], $check->licenseWarning);
            }

            $closed = [];

            foreach ([$check->vehicleCurrent, $check->driverCurrent] as $current) {
                if ($current !== null) {
                    $closed[] = $this->end->handle($current, $startsAt);
                }
            }

            $assignment = VehicleAssignment::query()->create([
                'vehicle_id' => $vehicle->getKey(),
                'driver_id' => $driver->getKey(),
                'starts_at' => $startsAt,
                'notes' => filled($notes) ? $notes : null,
            ]);

            return new AssignmentResult($assignment, $closed, $check->licenseWarning);
        });
    }

    /**
     * @throws ValidationException
     */
    private function ensureAssignable(Vehicle $vehicle, Driver $driver): void
    {
        // Both must belong to the active company: a model loaded some other
        // way must never produce an assignment that mixes companies.
        $companyId = CompanyContext::requireId(VehicleAssignment::class);

        if ($vehicle->company_id !== $companyId || $driver->company_id !== $companyId) {
            throw MissingCompanyContext::crossCompanyWrite(VehicleAssignment::class);
        }

        $error = match (true) {
            $vehicle->trashed() || $vehicle->isRetired() => __('core::assignments.errors.vehicle_retired'),
            $driver->trashed() => __('core::assignments.errors.not_a_driver'),
            ! $driver->person->isActive() || $driver->person->trashed() => __('core::assignments.errors.person_inactive'),
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['assignment' => $error]);
        }
    }
}
