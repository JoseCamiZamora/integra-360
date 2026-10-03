<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Enums\IdentityDocumentType;
use Modules\Core\Enums\PersonStatus;
use Modules\Core\Models\Person;

/**
 * Fictitious people. Created inside CompanyContext::run() (company_id comes
 * from the context).
 *
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        return [
            'document_type' => IdentityDocumentType::CC,
            'document_number' => fake()->unique()->numerify('99########'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => '3'.fake()->numerify('#########'),
            'position' => 'Conductor',
            'area' => 'Operaciones',
            'hired_at' => fake()->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
            'status' => PersonStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => PersonStatus::Inactive]);
    }
}
