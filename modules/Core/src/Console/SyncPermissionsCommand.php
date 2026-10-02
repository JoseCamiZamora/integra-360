<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Modules\Core\Actions\SyncPermissions;

final class SyncPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync
        {--reset-grants : Restore every role to the default permissions declared by the modules}';

    protected $description = 'Register the permissions declared in each module\'s permissions.php and the initial roles';

    public function handle(SyncPermissions $sync): int
    {
        $result = $sync->handle((bool) $this->option('reset-grants'));

        $this->components->info(sprintf(
            'Permissions synchronised: %d created, %d deleted.',
            count($result['created']),
            count($result['deleted']),
        ));

        foreach ($result['deleted'] as $name) {
            $this->components->warn("Deleted: {$name}");
        }

        return self::SUCCESS;
    }
}
