<?php

declare(strict_types=1);

use App\Modules\ModuleScaffolder;
use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->files = new Filesystem;
    $this->root = storage_path('framework/testing/module-scaffolder-'.uniqid());

    // Work on a copy of the files the scaffolder touches, never on the real ones.
    $this->files->ensureDirectoryExists($this->root.'/config');
    $this->files->copy(base_path('composer.json'), $this->root.'/composer.json');
    $this->files->copy(base_path('config/modules.php'), $this->root.'/config/modules.php');
    $this->files->copyDirectory(base_path('stubs/module'), $this->root.'/stubs/module');
});

afterEach(function (): void {
    $this->files->deleteDirectory($this->root);
});

it('creates a module with the standard structure', function (): void {
    $path = app(ModuleScaffolder::class)->handle('HumanResources', $this->root);

    expect($path)->toBe($this->root.'/modules/HumanResources');

    foreach ([
        'src/Models', 'src/Actions', 'src/Contracts', 'src/Events', 'src/Listeners', 'src/Policies',
        'src/Filament/Resources', 'src/Filament/Pages', 'src/Filament/Widgets', 'src/Livewire',
        'database/migrations', 'database/seeders', 'database/factories', 'resources/views/livewire',
    ] as $directory) {
        expect($path.'/'.$directory)->toBeDirectory();
    }

    expect(file_get_contents($path.'/src/Providers/HumanResourcesServiceProvider.php'))
        ->toContain('namespace Modules\HumanResources\Providers;')
        ->toContain("return 'human-resources';")
        ->and(file_get_contents($path.'/lang/es/module.php'))->toContain("'name' => 'Human Resources'")
        ->and($path.'/routes/web.php')->toBeFile()
        ->and(file_get_contents($path.'/permissions.php'))->toContain('human-resources.resource.action')
        ->and(file_get_contents($path.'/tests/Feature/ModuleTest.php'))->toContain('HumanResourcesServiceProvider');
});

it('registers the module namespace in composer.json', function (): void {
    app(ModuleScaffolder::class)->handle('HumanResources', $this->root);

    $psr4 = json_decode((string) file_get_contents($this->root.'/composer.json'), true)['autoload']['psr-4'];

    expect($psr4)
        ->toHaveKey('Modules\\HumanResources\\', 'modules/HumanResources/src/')
        ->toHaveKey('Modules\\HumanResources\\Database\\Factories\\', 'modules/HumanResources/database/factories/')
        ->toHaveKey('Modules\\HumanResources\\Database\\Seeders\\', 'modules/HumanResources/database/seeders/')
        ->toHaveKey('Modules\\Core\\', 'modules/Core/src/');
});

it('registers the module in config/modules.php as licensable', function (): void {
    app(ModuleScaffolder::class)->handle('HumanResources', $this->root);

    $source = (string) file_get_contents($this->root.'/config/modules.php');
    $modules = require $this->root.'/config/modules.php';

    expect($source)->toContain('use Modules\HumanResources\Providers\HumanResourcesServiceProvider;')
        ->and($modules['modules'])->toHaveKeys(['core', 'pesv', 'human-resources'])
        ->and($modules['modules']['human-resources'])->toBe([
            'name' => 'human-resources::module.name',
            'provider' => 'Modules\HumanResources\Providers\HumanResourcesServiceProvider',
            'licensable' => true,
        ]);
});

it('rejects names that are not StudlyCase', function (string $name): void {
    app(ModuleScaffolder::class)->handle($name, $this->root);
})->throws(InvalidArgumentException::class)->with(['pesv', 'human_resources', 'Human Resources', '1Module']);

it('refuses to overwrite an existing module', function (): void {
    app(ModuleScaffolder::class)->handle('HumanResources', $this->root);
    app(ModuleScaffolder::class)->handle('HumanResources', $this->root);
})->throws(InvalidArgumentException::class, 'already exists');
