<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Enums\CompanyMissionType;
use Modules\Core\Models\Company;
use Modules\Core\Support\Nit;

/**
 * Fictitious companies only: never real pilot data.
 *
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'legal_name' => $name.' S.A.S.',
            'trade_name' => $name,
            'nit' => Nit::withVerificationDigit((string) fake()->unique()->numberBetween(900_000_000, 999_999_999)),
            'mission_type' => CompanyMissionType::Transport,
            'city' => 'Bogotá',
            'department' => 'Cundinamarca',
            'address' => fake()->streetAddress(),
            'phone' => '601'.fake()->numerify('#######'),
            'email' => fake()->unique()->safeEmail(),
            'legal_representative' => fake()->name(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
