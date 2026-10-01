<?php

declare(strict_types=1);

namespace App\Modules;

/**
 * Immutable description of one module, as declared in config/modules.php.
 */
final readonly class ModuleDefinition
{
    /**
     * @param  class-string<ModuleServiceProvider>  $provider
     */
    public function __construct(
        public string $code,
        public string $nameKey,
        public string $provider,
        public bool $licensable,
    ) {}

    /**
     * @param  array{name: string, provider: class-string<ModuleServiceProvider>, licensable?: bool}  $config
     */
    public static function fromConfig(string $code, array $config): self
    {
        return new self(
            code: $code,
            nameKey: $config['name'],
            provider: $config['provider'],
            licensable: $config['licensable'] ?? true,
        );
    }

    /**
     * Visible (translated) module name.
     */
    public function name(): string
    {
        $name = __($this->nameKey);

        return is_string($name) ? $name : $this->nameKey;
    }
}
