<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Actions\SyncPermissions;

/**
 * Initial roles and every module's permissions (same as permissions:sync).
 */
final class RolesAndPermissionsSeeder extends Seeder
{
    public function run(SyncPermissions $sync): void
    {
        $sync->handle();
    }
}
