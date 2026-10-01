<?php

declare(strict_types=1);

namespace App\Modules;

use InvalidArgumentException;

/**
 * Central list of the modules declared in config/modules.php.
 */
final class ModuleRegistry
{
    /**
     * @var array<string, ModuleDefinition>
     */
    private array $modules = [];

    /**
     * @param  array<string, array{name: string, provider: class-string<ModuleServiceProvider>, licensable?: bool}>  $config
     */
    public function __construct(array $config)
    {
        foreach ($config as $code => $definition) {
            $this->modules[$code] = ModuleDefinition::fromConfig($code, $definition);
        }
    }

    /**
     * @return array<string, ModuleDefinition>
     */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * @return array<string, ModuleDefinition>
     */
    public function licensable(): array
    {
        return array_filter($this->modules, fn (ModuleDefinition $module): bool => $module->licensable);
    }

    public function has(string $code): bool
    {
        return isset($this->modules[$code]);
    }

    public function get(string $code): ModuleDefinition
    {
        return $this->modules[$code]
            ?? throw new InvalidArgumentException("Module [{$code}] is not registered in config/modules.php.");
    }
}
