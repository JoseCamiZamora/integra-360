<?php

declare(strict_types=1);

use App\Modules\ModuleRegistry;
use Modules\Pesv\Providers\PesvServiceProvider;

it('loads the Pesv module from modules/', function (): void {
    $registry = app(ModuleRegistry::class);
    $provider = app()->getProvider(PesvServiceProvider::class);

    expect($registry->has('pesv'))->toBeTrue()
        ->and($registry->get('pesv')->name())->toBe('PESV')
        ->and($provider)->toBeInstanceOf(PesvServiceProvider::class)
        ->and(realpath($provider->modulePath()))->toBe(realpath(base_path('modules/Pesv')));
});
