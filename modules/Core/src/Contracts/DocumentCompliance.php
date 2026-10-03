<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Core\Models\Person;
use Modules\Core\Models\Vehicle;

/**
 * Document status of people and vehicles of the active company. Public API
 * of Core: the PESV uses it in every inspection and in its dashboard, so it
 * loads everything at once (a fixed number of queries, also for lists).
 *
 * $at defaults to now; dates are compared in Bogotá (DocumentStatus).
 */
interface DocumentCompliance
{
    public function forVehicle(Vehicle $vehicle, ?CarbonInterface $at = null): ComplianceReport;

    /**
     * Required person documents are only demanded from drivers.
     */
    public function forPerson(Person $person, ?CarbonInterface $at = null): ComplianceReport;

    /**
     * @param  Collection<int, Person>  $people
     * @return array<string, ComplianceReport>
     */
    public function forPeople(Collection $people, ?CarbonInterface $at = null): array;

    /**
     * One report per vehicle, keyed by vehicle id, without one query per
     * vehicle (lists and dashboards).
     *
     * @param  Collection<int, Vehicle>  $vehicles
     * @return array<string, ComplianceReport>
     */
    public function forVehicles(Collection $vehicles, ?CarbonInterface $at = null): array;
}
