<?php

declare(strict_types=1);

use App\Modules\ModuleRegistry;
use Filament\Facades\Filament;

it('loads Core and Pesv from modules/', function (): void {
    $registry = app(ModuleRegistry::class);

    // Other modules may exist; Core and Pesv must always be there.
    expect($registry->all())->toHaveKeys(['core', 'pesv'])
        ->and($registry->get('core')->licensable)->toBeFalse()
        ->and($registry->licensable())->toHaveKey('pesv')->not->toHaveKey('core');
});

it('registers each module navigation group in the admin panel', function (): void {
    $groups = Filament::getPanel('admin')->getNavigationGroups();

    expect($groups)->toHaveKeys(['core', 'pesv'])
        ->and($groups['pesv']->getLabel())->toBe('PESV');
});

it('fails with a clear message for an unknown module', function (): void {
    app(ModuleRegistry::class)->get('sagrilaft');
})->throws(InvalidArgumentException::class, 'not registered');
