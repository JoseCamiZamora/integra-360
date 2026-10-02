<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use App\Modules\ModuleRegistry;
use Illuminate\Database\Seeder;
use Modules\Core\Models\Module;

/**
 * Product catalogue. Codes match config/modules.php (kebab-case), so a
 * module's license code is its module code.
 */
final class ModuleCatalogSeeder extends Seeder
{
    /**
     * code => [visible name, licensable]
     */
    private const array MODULES = [
        'core' => ['Núcleo', false],
        'pesv' => ['PESV', true],
        'sgsst' => ['SG-SST', true],
        'human-resources' => ['Talento Humano', true],
        'sagrilaft' => ['SAGRILAFT', true],
        'quality' => ['Calidad', true],
    ];

    public function run(ModuleRegistry $registry): void
    {
        foreach (self::MODULES as $code => [$name, $licensable]) {
            Module::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'is_licensable' => $licensable,
                'is_available' => $registry->has($code),
            ]);
        }
    }
}
