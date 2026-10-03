<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\VehicleTypeLicenseCategory;

/**
 * Initial license categories allowed for each vehicle type (production and
 * development). TO BE VALIDATED WITH THE PILOT COMPANY: these are working
 * values, not the legal rule; a category outside the list only warns when
 * assigning.
 *
 * Only seeds an empty table: ProductionSeeder runs on every deploy, and
 * re-adding missing pairs would undo the removals made by the platform
 * administrator.
 */
final class LicenseCategoryEquivalenceSeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    public const array CATEGORIES = [
        'motocicleta' => ['A1', 'A2'],
        'automovil' => ['B1', 'B2', 'B3', 'C1', 'C2', 'C3'],
        'camioneta' => ['B1', 'B2', 'B3', 'C1', 'C2', 'C3'],
        'microbus' => ['B2', 'B3', 'C1', 'C2', 'C3'],
        'buseta' => ['B2', 'B3', 'C2', 'C3'],
        'bus' => ['B2', 'B3', 'C2', 'C3'],
        'rigido' => ['B2', 'B3', 'C2', 'C3'],
        'volqueta' => ['B2', 'B3', 'C2', 'C3'],
        'tractocamion' => ['B3', 'C3'],
        'semirremolque' => ['B3', 'C3'],
    ];

    public function run(): void
    {
        if (VehicleTypeLicenseCategory::query()->exists()) {
            return;
        }

        foreach (self::CATEGORIES as $vehicleType => $categories) {
            foreach ($categories as $category) {
                VehicleTypeLicenseCategory::query()->create([
                    'vehicle_type' => $vehicleType,
                    'license_category' => $category,
                ]);
            }
        }
    }
}
