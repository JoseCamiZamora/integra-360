<?php

declare(strict_types=1);

use App\Modules\ModuleRegistry;
use Filament\Facades\Filament;

it('loads Core and Pesv from modules/', function (): void {
    $registry = app(ModuleRegistry::class);

    expect(array_keys($registry->all()))->toBe(['core', 'pesv'])
        ->and($registry->get('core')->licensable)->toBeFalse()
        ->and(array_keys($registry->licensable()))->toBe(['pesv']);
});

it('registers each module navigation group in the admin panel', function (): void {
    $groups = Filament::getPanel('admin')->getNavigationGroups();

    expect($groups)->toHaveKeys(['core', 'pesv'])
        ->and($groups['pesv']->getLabel())->toBe('PESV');
});

it('fails with a clear message for an unknown module', function (): void {
    app(ModuleRegistry::class)->get('sagrilaft');
})->throws(InvalidArgumentException::class, 'not registered');
