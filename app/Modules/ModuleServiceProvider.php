<?php

declare(strict_types=1);

namespace App\Modules;

use Closure;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use ReflectionClass;

/**
 * Base provider for every module in modules/<Module>.
 *
 * Loads the module's migrations, routes, views, translations, Livewire
 * components and Filament resources from its own folder, so a module only
 * has to declare its code. Everything is resolved by convention:
 *
 *   modules/<Module>/src/Providers/<Module>ServiceProvider.php
 *   namespace Modules\<Module>\...
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    public const string ADMIN_PANEL_ID = 'admin';

    /**
     * Extra route middleware for licensable modules (sign-in, active company,
     * license check). Core sets it, so the Kernel does not depend on Core.
     *
     * @var (Closure(string): list<string>)|null
     */
    private static ?Closure $licensableRouteMiddleware = null;

    /**
     * @param  Closure(string): list<string>  $middleware  receives the module code
     */
    public static function guardLicensableRoutesWith(Closure $middleware): void
    {
        self::$licensableRouteMiddleware = $middleware;
    }

    /**
     * Module code as registered in config/modules.php (e.g. "pesv").
     * Also used as the view, translation, Livewire and navigation namespace.
     */
    abstract public function code(): string;

    public function register(): void
    {
        $this->registerFilamentComponents();
    }

    public function boot(): void
    {
        $path = $this->modulePath();

        $this->loadMigrationsFrom($path.'/database/migrations');
        $this->loadViewsFrom($path.'/resources/views', $this->code());
        $this->loadTranslationsFrom($path.'/lang', $this->code());

        if (is_file($path.'/routes/web.php') && ! $this->app->routesAreCached()) {
            Route::middleware(['web', ...$this->routeMiddleware()])->group($path.'/routes/web.php');
        }

        Livewire::addNamespace(
            $this->code(),
            classNamespace: $this->moduleNamespace().'\\Livewire',
            classPath: $path.'/src/Livewire',
            classViewPath: $path.'/resources/views/livewire',
        );
    }

    /**
     * The module's permissions: modules/<Module>/permissions.php, an array of
     * "module.resource.action" => default roles. Registered by
     * `php artisan permissions:sync`.
     */
    public function permissionsPath(): string
    {
        return $this->modulePath('permissions.php');
    }

    public function isLicensable(): bool
    {
        $registry = $this->app->make(ModuleRegistry::class);

        return $registry->has($this->code()) && $registry->get($this->code())->licensable;
    }

    /**
     * @return list<string>
     */
    protected function routeMiddleware(): array
    {
        if (! $this->isLicensable() || self::$licensableRouteMiddleware === null) {
            return [];
        }

        return (self::$licensableRouteMiddleware)($this->code());
    }

    /**
     * Absolute path to modules/<Module>, optionally joined with $path.
     */
    public function modulePath(string $path = ''): string
    {
        $file = (string) (new ReflectionClass($this))->getFileName();
        $root = dirname($file, 3);

        return $path === '' ? $root : $root.'/'.ltrim($path, '/');
    }

    /**
     * Root PHP namespace of the module, e.g. "Modules\Pesv".
     */
    public function moduleNamespace(): string
    {
        return implode('\\', array_slice(explode('\\', static::class), 0, 2));
    }

    /**
     * Discovers the module's Filament resources, pages and widgets and
     * registers its navigation group (keyed by the module code, so
     * resources declare `$navigationGroup = '<code>'`).
     */
    protected function registerFilamentComponents(): void
    {
        $path = $this->modulePath('src/Filament');
        $namespace = $this->moduleNamespace().'\\Filament';
        $code = $this->code();

        Panel::configureUsing(function (Panel $panel) use ($path, $namespace, $code): void {
            if ($panel->getId() !== self::ADMIN_PANEL_ID) {
                return;
            }

            $panel
                ->discoverResources(in: $path.'/Resources', for: $namespace.'\\Resources')
                ->discoverPages(in: $path.'/Pages', for: $namespace.'\\Pages')
                ->discoverWidgets(in: $path.'/Widgets', for: $namespace.'\\Widgets')
                ->navigationGroups([
                    $code => NavigationGroup::make()->label(fn (): string => $this->moduleName()),
                ]);
        });
    }

    protected function moduleName(): string
    {
        $registry = $this->app->make(ModuleRegistry::class);

        return $registry->has($this->code()) ? $registry->get($this->code())->name() : $this->code();
    }
}
