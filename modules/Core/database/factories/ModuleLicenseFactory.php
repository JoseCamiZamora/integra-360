<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\ModuleLicense;

/**
 * Created inside CompanyContext::run(). The module must exist in `modules`.
 *
 * @extends Factory<ModuleLicense>
 */
class ModuleLicenseFactory extends Factory
{
    protected $model = ModuleLicense::class;

    public function definition(): array
    {
        return [
            'module_code' => 'pesv',
            'starts_at' => now()->subMonth()->toDateString(),
            'ends_at' => null,
            'max_vehicles' => null,
            'max_people' => null,
            'is_active' => true,
        ];
    }
}
