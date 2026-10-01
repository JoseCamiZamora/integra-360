<?php

declare(strict_types=1);

use App\Modules\ModuleRegistry;
use Modules\Core\Providers\CoreServiceProvider;

it('loads the Core module from modules/', function (): void {
    $registry = app(ModuleRegistry::class);
    $provider = app()->getProvider(CoreServiceProvider::class);

    expect($registry->has('core'))->toBeTrue()
        ->and($registry->get('core')->name())->toBe('Núcleo')
        ->and($provider)->toBeInstanceOf(CoreServiceProvider::class)
        ->and(realpath($provider->modulePath()))->toBe(realpath(base_path('modules/Core')));
});
