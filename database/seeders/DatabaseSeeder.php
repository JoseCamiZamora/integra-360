<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Database\Seeders\DevelopmentSeeder;
use Modules\Core\Database\Seeders\DocumentTypeCatalogSeeder;
use Modules\Core\Database\Seeders\LicenseCategoryEquivalenceSeeder;
use Modules\Core\Database\Seeders\ModuleCatalogSeeder;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;

/**
 * Local development (`php artisan migrate:fresh --seed`): catalogue, roles,
 * permissions and fictitious companies and users. Production uses
 * ProductionSeeder instead.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ModuleCatalogSeeder::class,
            RolesAndPermissionsSeeder::class,
            DocumentTypeCatalogSeeder::class,
            LicenseCategoryEquivalenceSeeder::class,
            DevelopmentSeeder::class,
        ]);
    }
}
