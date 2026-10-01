<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\ModuleScaffolder;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class MakeModuleCommand extends Command
{
    protected $signature = 'module:make {name : Module name in StudlyCase, e.g. HumanResources}';

    protected $description = 'Create a new module in modules/ with the standard structure';

    public function handle(ModuleScaffolder $scaffolder): int
    {
        $name = (string) $this->argument('name');

        try {
            $path = $scaffolder->handle($name, base_path());
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Module [{$name}] created in [{$path}].");
        $this->components->bulletList([
            'Run `composer dump-autoload` to load the new namespace.',
            "Run `php artisan test modules/{$name}` to check that it loads.",
            'Review its entry in config/modules.php (name, licensable).',
        ]);

        return self::SUCCESS;
    }
}
