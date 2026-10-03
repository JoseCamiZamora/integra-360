<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Database\Seeders\DocumentTypeCatalogSeeder;
use Modules\Core\Database\Seeders\LicenseCategoryEquivalenceSeeder;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Filament\Resources\Shared\DocumentsRelationManager;
use Modules\Core\Filament\Resources\Vehicles\Pages\CreateVehicle;
use Modules\Core\Filament\Resources\Vehicles\Pages\EditVehicle;
use Modules\Core\Filament\Resources\Vehicles\Pages\ListVehicles;
use Modules\Core\Filament\Resources\Vehicles\RelationManagers\AssignmentsRelationManager;
use Modules\Core\Models\Driver;
use Modules\Core\Models\ModuleLicense;
use Modules\Core\Models\Person;
use Modules\Core\Models\Vehicle;
use Modules\Core\Models\VehicleAssignment;

/*
 * The vehicles screens: list with document status, create and edit through
 * the actions, assigning a driver, and the read-only mode of an expired
 * license on every write action (Livewire calls included).
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedCore();
    app(DocumentTypeCatalogSeeder::class)->run();
    app(LicenseCategoryEquivalenceSeeder::class)->run();

    $this->company = createCompany();
    $this->admin = createMember($this->company, CompanyRole::CompanyAdmin);

    actingInPanel($this->admin, $this->company);

    $this->vehicle = Vehicle::factory()->create(['plate' => 'TST001']);
    $this->driver = Driver::factory()->for(Person::factory()->state(['first_name' => 'Carla', 'last_name' => 'Prueba']))->create(['license_category' => 'C1']);
});

afterEach(fn () => CompanyContext::forget());

it('lists the vehicles of the company with their document status', function (): void {
    $other = createCompany();
    $foreign = CompanyContext::run($other, fn () => Vehicle::factory()->create(['plate' => 'TST900']));

    Livewire::test(ListVehicles::class)
        ->assertCanSeeTableRecords([$this->vehicle])
        ->assertCanNotSeeTableRecords([$foreign])
        ->assertSee('TST001')
        ->assertSee('No cumple')
        ->assertDontSee('TST900');

    $this->get('/app/'.$this->company->getKey().'/vehicles')->assertOk()->assertSee('Operación');
});

it('creates a vehicle through the action, normalising the plate', function (): void {
    Livewire::test(CreateVehicle::class)
        ->fillForm([
            'plate' => 'tst-002',
            'vehicle_type' => 'volqueta',
            'brand' => 'Marca de prueba',
            'model_line' => 'Línea 2',
            'model_year' => 2019,
            'status' => 'active',
            'ownership' => 'own',
            'service_type' => 'cargo',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Vehicle::query()->where('plate', 'TST002')->exists())->toBeTrue();
});

it('shows the plate errors of the action next to the field', function (): void {
    Livewire::test(CreateVehicle::class)
        ->fillForm([
            'plate' => 'TST001',
            'vehicle_type' => 'rigido',
            'brand' => 'Marca',
            'model_line' => 'Línea',
            'model_year' => 2019,
            'status' => 'active',
            'ownership' => 'own',
            'service_type' => 'cargo',
        ])
        ->call('create')
        ->assertHasFormErrors(['plate']);
});

it('blocks creating beyond the license limit with a message', function (): void {
    createLicense($this->company, ['module_code' => 'pesv', 'max_vehicles' => 1]);

    Livewire::test(CreateVehicle::class)
        ->fillForm([
            'plate' => 'TST003',
            'vehicle_type' => 'rigido',
            'brand' => 'Marca',
            'model_line' => 'Línea',
            'model_year' => 2019,
            'status' => 'active',
            'ownership' => 'own',
            'service_type' => 'cargo',
        ])
        ->call('create')
        ->assertNotified();

    expect(Vehicle::query()->count())->toBe(1);
});

it('edits a vehicle and shows its documents and assignment history', function (): void {
    Livewire::test(EditVehicle::class, ['record' => $this->vehicle->getKey()])
        ->assertSchemaStateSet(['plate' => 'TST001'])
        ->fillForm(['color' => 'Verde'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->vehicle->fresh()->color)->toBe('Verde');

    Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $this->vehicle, 'pageClass' => EditVehicle::class])
        ->assertSuccessful();

    Livewire::test(AssignmentsRelationManager::class, ['ownerRecord' => $this->vehicle, 'pageClass' => EditVehicle::class])
        ->assertSuccessful();
});

it('assigns a driver and warns about the license category without blocking', function (): void {
    Livewire::test(ListVehicles::class)
        ->callTableAction('assignDriver', $this->vehicle->getKey(), [
            'driver_id' => $this->driver->getKey(),
            'starts_at' => now()->toDateTimeString(),
        ])
        ->assertHasNoTableActionErrors()
        ->assertNotified();

    expect($this->vehicle->fresh()->currentAssignment?->driver_id)->toBe($this->driver->getKey());
});

it('lets read-only roles see vehicles without any write action', function (): void {
    $viewer = createMember($this->company, CompanyRole::Viewer);
    actingInPanel($viewer->forgetCompanyRoles(), $this->company);

    Livewire::test(ListVehicles::class)
        ->assertCanSeeTableRecords([$this->vehicle])
        ->assertActionHidden('create')
        ->assertTableActionHidden(EditAction::class, $this->vehicle->getKey())
        ->assertTableActionHidden('assignDriver', $this->vehicle->getKey())
        ->assertTableActionHidden(DeleteAction::class, $this->vehicle->getKey());
});

describe('read-only period of an expired license', function (): void {
    beforeEach(function (): void {
        createLicense($this->company, [
            'module_code' => 'pesv',
            'starts_at' => now()->subYear()->toDateString(),
            'ends_at' => now()->subDays(5)->toDateString(),
        ]);
    });

    it('still shows the vehicles, hiding every write action', function (): void {
        Livewire::test(ListVehicles::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$this->vehicle])
            ->assertActionHidden('create')
            ->assertTableActionHidden(EditAction::class, $this->vehicle->getKey())
            ->assertTableActionHidden('assignDriver', $this->vehicle->getKey())
            ->assertTableActionHidden(DeleteAction::class, $this->vehicle->getKey());

        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $this->vehicle, 'pageClass' => EditVehicle::class])
            ->assertSuccessful()
            ->assertTableActionHidden('register');
    });

    it('rejects write actions called directly through Livewire', function (): void {
        // A crafted Livewire request (a POST to /livewire/update) can still name
        // a hidden action and its data: Filament must refuse to run it.
        $context = ['table' => true, 'recordKey' => $this->vehicle->getKey()];

        Livewire::test(ListVehicles::class)
            ->call('mountAction', 'assignDriver', [], $context)
            ->set('mountedActions.0.data', [
                'driver_id' => $this->driver->getKey(),
                'starts_at' => now()->toDateTimeString(),
            ])
            ->call('callMountedAction');

        Livewire::test(ListVehicles::class)
            ->call('mountAction', 'delete', [], $context)
            ->call('callMountedAction');

        expect(VehicleAssignment::query()->count())->toBe(0)
            ->and($this->vehicle->fresh()->trashed())->toBeFalse();
    });

    it('does not open the create or edit pages', function (): void {
        $this->get('/app/'.$this->company->getKey().'/vehicles/create')->assertForbidden();
        $this->get('/app/'.$this->company->getKey().'/vehicles/'.$this->vehicle->getKey().'/edit')->assertForbidden();
        $this->get('/app/'.$this->company->getKey().'/vehicles')->assertOk();
    });

    it('writes again once the license is renewed, through the same Livewire calls', function (): void {
        ModuleLicense::query()->sole()->update(['ends_at' => now()->addYear()->toDateString()]);

        // Positive control of the previous test: the same low-level calls do
        // run the action when it is allowed.
        Livewire::test(ListVehicles::class)
            ->assertActionVisible('create')
            ->call('mountAction', 'assignDriver', [], ['table' => true, 'recordKey' => $this->vehicle->getKey()])
            ->set('mountedActions.0.data', [
                'driver_id' => $this->driver->getKey(),
                'starts_at' => now()->toDateTimeString(),
            ])
            ->call('callMountedAction');

        expect(VehicleAssignment::query()->current()->count())->toBe(1);
    });
});
