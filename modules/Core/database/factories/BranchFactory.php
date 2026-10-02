<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Branch;

/**
 * Created inside CompanyContext::run() (company_id comes from the context).
 *
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'name' => 'Sede '.fake()->unique()->city(),
            'city' => fake()->city(),
            'address' => fake()->streetAddress(),
            'phone' => '3'.fake()->numerify('#########'),
            'is_active' => true,
        ];
    }
}
