<?php

declare(strict_types=1);

namespace App\Modules;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Generates a new module from stubs/module so that every module is born with
 * the same structure, and wires it into composer.json and config/modules.php.
 */
final class ModuleScaffolder
{
    public const string CONFIG_MARKER = '// module:make:append';

    /**
     * Empty folders every module must have (kept in git with a .gitkeep).
     */
    private const array DIRECTORIES = [
        'src/Models',
        'src/Actions',
        'src/Contracts',
        'src/Events',
        'src/Listeners',
        'src/Policies',
        'src/Filament/Resources',
        'src/Filament/Pages',
        'src/Filament/Widgets',
        'src/Livewire',
        'database/migrations',
        'database/seeders',
        'database/factories',
        'resources/views/livewire',
    ];

    /**
     * Stub file => destination inside the module.
     */
    private const array FILES = [
        'provider.stub' => 'src/Providers/{{ module }}ServiceProvider.php',
        'routes.stub' => 'routes/web.php',
        'lang.stub' => 'lang/es/module.php',
        'permissions.stub' => 'permissions.php',
        'test.stub' => 'tests/Feature/ModuleTest.php',
    ];

    public function __construct(
        private readonly Filesystem $files,
    ) {}

    /**
     * @return string Absolute path of the created module.
     */
    public function handle(string $name, string $basePath): string
    {
        $module = $this->validateName($name);
        $code = Str::kebab($module);
        $modulePath = $basePath.'/modules/'.$module;

        if ($this->files->exists($modulePath)) {
            throw new InvalidArgumentException("The module [{$module}] already exists in modules/{$module}.");
        }

        $replacements = [
            '{{ module }}' => $module,
            '{{ code }}' => $code,
            '{{ name }}' => Str::headline($module),
        ];

        foreach (self::DIRECTORIES as $directory) {
            $this->files->ensureDirectoryExists($modulePath.'/'.$directory);
            $this->files->put($modulePath.'/'.$directory.'/.gitkeep', '');
        }

        foreach (self::FILES as $stub => $destination) {
            $target = $modulePath.'/'.strtr($destination, $replacements);
            $this->files->ensureDirectoryExists(dirname($target));
            $this->files->put($target, strtr($this->files->get($this->stubPath($basePath, $stub)), $replacements));
        }

        $this->registerAutoload($basePath, $module);
        $this->registerInConfig($basePath, $module, $code);

        return $modulePath;
    }

    private function validateName(string $name): string
    {
        $module = Str::studly($name);

        if (! preg_match('/^[A-Z][A-Za-z0-9]*$/', $module) || $module !== $name) {
            throw new InvalidArgumentException("The module name must be in StudlyCase (e.g. HumanResources); [{$name}] given.");
        }

        return $module;
    }

    private function stubPath(string $basePath, string $stub): string
    {
        return $basePath.'/stubs/module/'.$stub;
    }

    /**
     * @throws JsonException
     */
    private function registerAutoload(string $basePath, string $module): void
    {
        $path = $basePath.'/composer.json';

        /** @var array{autoload: array{'psr-4': array<string, string>}} $composer */
        $composer = json_decode($this->files->get($path), true, flags: JSON_THROW_ON_ERROR);

        $composer['autoload']['psr-4'] += [
            "Modules\\{$module}\\" => "modules/{$module}/src/",
            "Modules\\{$module}\\Database\\Factories\\" => "modules/{$module}/database/factories/",
            "Modules\\{$module}\\Database\\Seeders\\" => "modules/{$module}/database/seeders/",
        ];

        $json = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->files->put($path, $json."\n");
    }

    private function registerInConfig(string $basePath, string $module, string $code): void
    {
        $path = $basePath.'/config/modules.php';
        $config = $this->files->get($path);

        if (! str_contains($config, self::CONFIG_MARKER)) {
            throw new RuntimeException('Marker ['.self::CONFIG_MARKER.'] not found in config/modules.php.');
        }

        $entry = <<<PHP
        '{$code}' => [
                    'name' => '{$code}::module.name',
                    'provider' => {$module}ServiceProvider::class,
                    'licensable' => true,
                ],
        PHP;

        $indent = str_repeat(' ', 8);

        $config = str_replace(self::CONFIG_MARKER, $entry."\n\n".$indent.self::CONFIG_MARKER, $config);

        $this->files->put($path, $this->addImport($config, "Modules\\{$module}\\Providers\\{$module}ServiceProvider"));
    }

    /**
     * Adds a `use` statement keeping the alphabetical order Pint enforces.
     */
    private function addImport(string $php, string $class): string
    {
        preg_match_all('/^use [^;]+;\n/m', $php, $matches);

        $imports = $matches[0];
        $imports[] = "use {$class};\n";
        $imports = array_unique($imports);
        sort($imports, SORT_STRING | SORT_FLAG_CASE);

        $block = implode('', $imports);
        $php = (string) preg_replace('/^use [^;]+;\n\n?/m', '', $php);

        return (string) preg_replace_callback(
            '/^declare\(strict_types=1\);\n\n/m',
            fn (array $match): string => $match[0].$block."\n",
            $php,
            1,
        );
    }
}
