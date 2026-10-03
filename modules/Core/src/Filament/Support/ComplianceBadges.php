<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use Modules\Core\Contracts\ComplianceReport;
use Modules\Core\Contracts\DocumentCompliance;
use Modules\Core\Models\Person;
use Modules\Core\Models\Vehicle;

/**
 * Document status badges of list rows. The first row asks for every
 * vehicle (or person) of the company at once, so a list costs a fixed
 * number of queries instead of one per row. Bound as a scoped instance
 * (one request or Livewire update).
 */
final class ComplianceBadges
{
    /**
     * @var array<string, ComplianceReport>|null
     */
    private ?array $vehicles = null;

    /**
     * @var array<string, ComplianceReport>|null
     */
    private ?array $people = null;

    public function __construct(
        private readonly DocumentCompliance $compliance,
    ) {}

    public function vehicle(Vehicle $vehicle): ComplianceReport
    {
        $this->vehicles ??= $this->compliance->forVehicles(Vehicle::withTrashed()->get());

        return $this->vehicles[$vehicle->getKey()] ?? $this->compliance->forVehicle($vehicle);
    }

    public function person(Person $person): ComplianceReport
    {
        $this->people ??= $this->compliance->forPeople(Person::withTrashed()->get());

        return $this->people[$person->getKey()] ?? $this->compliance->forPerson($person);
    }
}
