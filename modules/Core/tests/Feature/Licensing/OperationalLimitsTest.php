<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Actions\CreatePerson;
use Modules\Core\Actions\CreateVehicle;
use Modules\Core\Actions\DeletePerson;
use Modules\Core\Actions\DeleteVehicle;
use Modules\Core\Actions\RestorePerson;
use Modules\Core\Actions\RestoreVehicle;
use Modules\Core\Actions\SetPersonStatus;
use Modules\Core\Actions\UpdateVehicle;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Enums\ModuleAccessLevel;
use Modules\Core\Enums\PersonStatus;
use Modules\Core\Enums\VehicleStatus;
use Modules\Core\Licensing\ModuleAccess;
use Modules\Core\Models\DocumentType;
use Modules\Core\Models\ExpiringDocument;
use Modules\Core\Models\ModuleLicense;
use Modules\Core\Models\Person;
use Modules\Core\Models\Vehicle;
use Modules\Core\Models\VehicleAssignment;

/*
 * Criterion 10: license limits for vehicles and people (effective limit
 * across licenses) and the read-only period of expired licenses.
 */

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function limitVehicleData(string $plate, array $overrides = []): array
{
    return array_merge([
        'plate' => $plate,
        'vehicle_type' => 'rigido',
        'brand' => 'Marca de prueba',
        'model_line' => 'Línea 1',
        'model_year' => 2021,
        'ownership' => 'own',
        'service_type' => 'cargo',
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function limitPersonData(string $document): array
{
    return [
        'document_type' => 'CC',
        'document_number' => $document,
        'first_name' => 'Persona',
        'last_name' => 'De Prueba',
        'position' => 'Auxiliar',
    ];
}

beforeEach(function (): void {
    seedCore();

    $this->company = createCompany();
    $this->admin = createMember($this->company, CompanyRole::CompanyAdmin);
    $this->actingAs($this->admin);
    CompanyContext::activate($this->company);

    // A fresh instance: ModuleAccess memoizes licenses for the request.
    $this->access = function (): ModuleAccess {
        app()->forgetInstance(ModuleAccess::class);

        return app(ModuleAccess::class);
    };
});

afterEach(fn () => CompanyContext::forget());

describe('effective limit', function (): void {
    it('has no limit without licenses of licensable modules', function (): void {
        $limits = ($this->access)()->effectiveLimits($this->company);

        expect($limits->maxVehicles)->toBeNull()->and($limits->maxPeople)->toBeNull();
    });

    it('takes the highest limit among active licenses, each limit separately', function (): void {
        createLicense($this->company, ['module_code' => 'pesv', 'max_vehicles' => 6, 'max_people' => 20]);
        createLicense($this->company, ['module_code' => 'sgsst', 'max_vehicles' => 10, 'max_people' => 12]);

        $limits = ($this->access)()->effectiveLimits($this->company);

        expect($limits->maxVehicles)->toBe(10)->and($limits->maxPeople)->toBe(20);
    });

    it('has no limit when any active license has none', function (): void {
        createLicense($this->company, ['module_code' => 'pesv', 'max_vehicles' => 6, 'max_people' => 10]);
        createLicense($this->company, ['module_code' => 'sgsst', 'max_vehicles' => null, 'max_people' => 5]);

        $limits = ($this->access)()->effectiveLimits($this->company);

        expect($limits->maxVehicles)->toBeNull()->and($limits->maxPeople)->toBe(10);
    });

    it('ignores inactive, future and expired licenses', function (): void {
        createLicense($this->company, ['module_code' => 'pesv', 'max_vehicles' => 3, 'max_people' => 3]);
        createLicense($this->company, ['module_code' => 'sgsst', 'max_vehicles' => null, 'is_active' => false]);
        createLicense($this->company, ['module_code' => 'sagrilaft', 'max_vehicles' => null, 'starts_at' => now()->addMonth()->toDateString()]);
        createLicense($this->company, ['module_code' => 'quality', 'max_vehicles' => null, 'ends_at' => now()->subDays(5)->toDateString()]);

        expect(($this->access)()->effectiveLimits($this->company)->maxVehicles)->toBe(3);
    });
});

describe('vehicles', function (): void {
    beforeEach(function (): void {
        createLicense($this->company, ['module_code' => 'pesv', 'max_vehicles' => 2]);

        $this->first = app(CreateVehicle::class)->handle(limitVehicleData('TST001'));
        $this->second = app(CreateVehicle::class)->handle(limitVehicleData('TST002'));
    });

    it('blocks creating vehicles beyond the limit with a clear message', function (): void {
        app(CreateVehicle::class)->handle(limitVehicleData('TST003'));
    })->throws(ValidationException::class, 'La licencia permite hasta 2 vehículos en servicio');

    it('keeps editing and reading at the limit', function (): void {
        app(UpdateVehicle::class)->handle($this->first, limitVehicleData('TST001', ['color' => 'Azul']));

        expect($this->first->fresh()->color)->toBe('Azul')
            ->and(Vehicle::query()->count())->toBe(2);
    });

    it('does not count retired or deleted vehicles', function (): void {
        app(UpdateVehicle::class)->handle($this->first, limitVehicleData('TST001', ['status' => VehicleStatus::Retired->value]));
        app(CreateVehicle::class)->handle(limitVehicleData('TST003'));

        app(DeleteVehicle::class)->handle($this->second);
        app(CreateVehicle::class)->handle(limitVehicleData('TST004'));

        expect(Vehicle::query()->inService()->count())->toBe(2)
            ->and(Vehicle::withTrashed()->count())->toBe(4);
    });

    it('applies the limit when putting a retired vehicle back in service', function (): void {
        app(UpdateVehicle::class)->handle($this->first, limitVehicleData('TST001', ['status' => VehicleStatus::Retired->value]));
        app(CreateVehicle::class)->handle(limitVehicleData('TST003'));

        app(UpdateVehicle::class)->handle($this->first->fresh(), limitVehicleData('TST001', ['status' => VehicleStatus::Active->value]));
    })->throws(ValidationException::class, 'hasta 2 vehículos');

    it('applies the limit when restoring a deleted vehicle', function (): void {
        app(DeleteVehicle::class)->handle($this->second);
        app(CreateVehicle::class)->handle(limitVehicleData('TST003'));

        expect(fn () => app(RestoreVehicle::class)->handle($this->second))->toThrow(ValidationException::class, 'hasta 2 vehículos');

        app(UpdateVehicle::class)->handle($this->first, limitVehicleData('TST001', ['status' => VehicleStatus::Retired->value]));

        expect(app(RestoreVehicle::class)->handle($this->second)->trashed())->toBeFalse();
    });

    it('never hides or deletes anything when the limit goes down', function (): void {
        ModuleLicense::query()->sole()->update(['max_vehicles' => 1]);

        expect(Vehicle::query()->count())->toBe(2)
            ->and(fn () => app(CreateVehicle::class)->handle(limitVehicleData('TST003')))->toThrow(ValidationException::class);

        app(UpdateVehicle::class)->handle($this->second, limitVehicleData('TST002', ['color' => 'Rojo']));

        expect($this->second->fresh()->color)->toBe('Rojo');
    });
});

describe('people', function (): void {
    beforeEach(function (): void {
        createLicense($this->company, ['module_code' => 'pesv', 'max_people' => 2]);

        $this->first = app(CreatePerson::class)->handle(limitPersonData('99000001'));
        $this->second = app(CreatePerson::class)->handle(limitPersonData('99000002'));
    });

    it('blocks creating people beyond the limit with a clear message', function (): void {
        app(CreatePerson::class)->handle(limitPersonData('99000003'));
    })->throws(ValidationException::class, 'La licencia permite hasta 2 personas activas');

    it('does not count retired people and applies the limit when reactivating', function (): void {
        app(SetPersonStatus::class)->handle($this->first, PersonStatus::Inactive);
        app(CreatePerson::class)->handle(limitPersonData('99000003'));

        expect(Person::query()->active()->count())->toBe(2)
            ->and(fn () => app(SetPersonStatus::class)->handle($this->first, PersonStatus::Active))
            ->toThrow(ValidationException::class, 'hasta 2 personas');

        expect($this->first->fresh()->status)->toBe(PersonStatus::Inactive);
    });

    it('restores a deleted person retired, so restoring never exceeds the limit', function (): void {
        app(DeletePerson::class)->handle($this->second);
        app(CreatePerson::class)->handle(limitPersonData('99000003'));

        $restored = app(RestorePerson::class)->handle($this->second);

        expect($restored->trashed())->toBeFalse()
            ->and($restored->status)->toBe(PersonStatus::Inactive)
            ->and(Person::query()->active()->count())->toBe(2);
    });

    it('applies the limit when restoring an active person', function (): void {
        $this->second->delete();
        app(CreatePerson::class)->handle(limitPersonData('99000003'));

        app(RestorePerson::class)->handle($this->second);
    })->throws(ValidationException::class, 'hasta 2 personas');
});

describe('read-only period of an expired license', function (): void {
    beforeEach(function (): void {
        createLicense($this->company, ['module_code' => 'pesv', 'starts_at' => now()->subYear()->toDateString(), 'ends_at' => now()->subDays(10)->toDateString()]);

        $this->vehicle = Vehicle::factory()->create();
        $this->person = Person::factory()->create();
    });

    it('is read-only for operational data, during and after the grace period', function (): void {
        expect(($this->access)()->operationalLevel($this->company))->toBe(ModuleAccessLevel::ReadOnly);

        ModuleLicense::query()->sole()->update(['ends_at' => now()->subDays(45)->toDateString()]);

        expect(($this->access)()->operationalLevel($this->company))->toBe(ModuleAccessLevel::ReadOnly);
    });

    it('is writable with an active license or without licensable licenses', function (): void {
        createLicense($this->company, ['module_code' => 'sgsst']);

        expect(($this->access)()->operationalLevel($this->company))->toBe(ModuleAccessLevel::Full)
            ->and(($this->access)()->operationalLevel(createCompany()))->toBe(ModuleAccessLevel::Full);
    });

    it('lets users read but not create or change anything', function (): void {
        $document = ExpiringDocument::factory()
            ->of($this->vehicle, DocumentType::factory()->create())
            ->create();
        $leader = createMember($this->company, CompanyRole::PesvLeader);

        foreach ([$this->admin, $leader] as $user) {
            expect($user->can('viewAny', Vehicle::class))->toBeTrue()
                ->and($user->can('view', $this->vehicle))->toBeTrue()
                ->and($user->can('view', $this->person))->toBeTrue()
                ->and($user->can('view', $document))->toBeTrue()
                ->and($user->can('create', Vehicle::class))->toBeFalse()
                ->and($user->can('update', $this->vehicle))->toBeFalse()
                ->and($user->can('delete', $this->vehicle))->toBeFalse()
                ->and($user->can('create', Person::class))->toBeFalse()
                ->and($user->can('update', $this->person))->toBeFalse()
                ->and($user->can('changeStatus', $this->person))->toBeFalse()
                ->and($user->can('createAccess', $this->person))->toBeFalse()
                ->and($user->can('create', ExpiringDocument::class))->toBeFalse()
                ->and($user->can('renew', $document))->toBeFalse()
                ->and($user->can('create', VehicleAssignment::class))->toBeFalse()
                ->and($user->can('create', DocumentType::class))->toBeFalse();
        }
    });
});
