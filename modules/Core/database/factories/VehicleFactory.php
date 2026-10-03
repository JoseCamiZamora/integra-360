<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Enums\VehicleOwnership;
use Modules\Core\Enums\VehicleServiceType;
use Modules\Core\Enums\VehicleStatus;
use Modules\Core\Enums\VehicleType;
use Modules\Core\Models\Vehicle;

/**
 * Fictitious vehicles: plates always start with TST. Created inside
 * CompanyContext::run() (company_id comes from the context).
 *
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'plate' => 'TST'.fake()->unique()->numerify('###'),
            'vehicle_type' => VehicleType::Tractocamion,
            'brand' => fake()->randomElement(['Marca Uno', 'Marca Dos', 'Marca Tres']),
            'model_line' => 'Línea '.fake()->numerify('##'),
            'model_year' => fake()->numberBetween(2010, 2026),
            'color' => 'Blanco',
            'load_capacity_kg' => 30000,
            'ownership' => VehicleOwnership::Own,
            'service_type' => VehicleServiceType::Cargo,
            'odometer_km' => fake()->numberBetween(10000, 900000),
            'status' => VehicleStatus::Active,
        ];
    }

    public function ofType(VehicleType $type): static
    {
        return $this->state(['vehicle_type' => $type]);
    }
}
