<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\ModuleRegistry;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, fn (): ModuleRegistry => new ModuleRegistry(
            (array) config('modules.modules', []),
        ));

        foreach ($this->app->make(ModuleRegistry::class)->all() as $module) {
            if (! class_exists($module->provider)) {
                throw new RuntimeException(
                    "Provider [{$module->provider}] of module [{$module->code}] not found. Run `composer dump-autoload`.",
                );
            }

            $this->app->register($module->provider);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->ensureUploadsAreNotStoredLocallyInProduction();
    }

    /**
     * Laravel Cloud's disk is ephemeral: user uploads must go to an
     * S3-compatible bucket in production. Fail fast instead of losing files.
     */
    private function ensureUploadsAreNotStoredLocallyInProduction(): void
    {
        if (! $this->app->isProduction()) {
            return;
        }

        $disk = (string) config('filesystems.default');

        if (config("filesystems.disks.{$disk}.driver") !== 's3') {
            throw new RuntimeException(
                "FILESYSTEM_DISK [{$disk}] is not S3-compatible. Attach a bucket in Laravel Cloud (see docs/deploy.md).",
            );
        }
    }
}
