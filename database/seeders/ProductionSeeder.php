<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Database\Seeders\DocumentTypeCatalogSeeder;
use Modules\Core\Database\Seeders\LicenseCategoryEquivalenceSeeder;
use Modules\Core\Database\Seeders\ModuleCatalogSeeder;
use Modules\Core\Database\Seeders\PlatformAdminSeeder;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;

/**
 * Production: only the module catalogue, roles, permissions, the global
 * document types, the license categories by vehicle type and the platform
 * administrator (PLATFORM_ADMIN_* variables). Safe to run on every deploy.
 *
 *   php artisan db:seed --class=ProductionSeeder --force
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ModuleCatalogSeeder::class,
            RolesAndPermissionsSeeder::class,
            DocumentTypeCatalogSeeder::class,
            LicenseCategoryEquivalenceSeeder::class,
            PlatformAdminSeeder::class,
        ]);
    }
}
