<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Enums\CompanyRole;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('registers the permissions every module declares and the initial roles', function (): void {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Role::query()->whereNull('team_id')->pluck('name')->sort()->values()->all())
        ->toBe(collect(CompanyRole::cases())->map->value->sort()->values()->all())
        ->and(Permission::query()->pluck('name')->all())
        ->toContain('core.users.create', 'core.audit.view', 'core.driver.access', 'pesv.overview.view');

    expect(Role::findByName('driver')->permissions->pluck('name')->all())->toBe(['core.driver.access'])
        ->and(Role::findByName('company_admin')->hasPermissionTo('core.users.reset-password'))->toBeTrue()
        ->and(Role::findByName('viewer')->hasPermissionTo('core.users.create'))->toBeFalse();
});

it('keeps grants changed later and restores them on request', function (): void {
    $this->artisan('permissions:sync');

    Role::findByName('viewer')->givePermissionTo('core.branches.create');

    $this->artisan('permissions:sync');
    expect(Role::findByName('viewer')->fresh()->hasPermissionTo('core.branches.create'))->toBeTrue();

    $this->artisan('permissions:sync', ['--reset-grants' => true]);
    expect(Role::findByName('viewer')->fresh()->hasPermissionTo('core.branches.create'))->toBeFalse();
});

it('deletes permissions that no module declares any more', function (): void {
    $this->artisan('permissions:sync');
    Permission::create(['name' => 'core.obsolete.view', 'guard_name' => 'web']);

    $this->artisan('permissions:sync')
        ->expectsOutputToContain('core.obsolete.view')
        ->assertSuccessful();

    expect(Permission::query()->where('name', 'core.obsolete.view')->exists())->toBeFalse();
});

it('names permissions as module.resource.action', function (): void {
    $this->artisan('permissions:sync');

    Permission::query()->pluck('name')->each(
        fn (string $name) => expect($name)->toMatch('/^[a-z-]+\.[a-z0-9-]+\.[a-z0-9-]+$/'),
    );
});
