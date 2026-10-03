<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Enums\LicenseCategory;
use Modules\Core\Models\Driver;
use Modules\Core\Models\Person;

/**
 * Driver profile; pass the person with for(). Created inside
 * CompanyContext::run().
 *
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'license_number' => fake()->unique()->numerify('99########'),
            'license_category' => LicenseCategory::C3,
            'experience_years' => fake()->numberBetween(1, 25),
        ];
    }
}
