<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Actions\CreateVehicle;
use Modules\Core\Actions\UpdateVehicle;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Enums\VehicleStatus;
use Modules\Core\Models\AuditEntry;
use Modules\Core\Models\Branch;
use Modules\Core\Models\User;
use Modules\Core\Models\Vehicle;

/*
 * Criterion 2 (plates) and criterion 1 for vehicles.
 */

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function vehicleData(array $overrides = []): array
{
    return array_merge([
        'plate' => 'TST001',
        'vehicle_type' => 'tractocamion',
        'brand' => 'Marca de prueba',
        'model_line' => 'Línea 10',
        'model_year' => 2020,
        'ownership' => 'own',
        'service_type' => 'cargo',
    ], $overrides);
}

beforeEach(function (): void {
    seedCore();

    $this->companyA = createCompany();
    $this->companyB = createCompany();
    $this->adminA = createMember($this->companyA, CompanyRole::CompanyAdmin);

    $this->actingAs($this->adminA);
    CompanyContext::activate($this->companyA);
});

afterEach(fn () => CompanyContext::forget());

describe('plates', function (): void {
    it('normalises and accepts the Colombian formats by vehicle type', function (string $typed, string $type, string $stored): void {
        $vehicle = app(CreateVehicle::class)->handle(vehicleData(['plate' => $typed, 'vehicle_type' => $type]));

        expect($vehicle->plate)->toBe($stored);
    })->with([
        'truck' => ['tst-001', 'tractocamion', 'TST001'],
        'with spaces' => [' tst 002 ', 'volqueta', 'TST002'],
        'motorcycle' => ['tst12a', 'motocicleta', 'TST12A'],
        'semitrailer R' => ['R-12345', 'semirremolque', 'R12345'],
        'semitrailer S' => ['s12345', 'semirremolque', 'S12345'],
    ]);

    it('rejects plates that do not match the format of the vehicle type', function (string $plate, string $type): void {
        expect(fn () => app(CreateVehicle::class)->handle(vehicleData(['plate' => $plate, 'vehicle_type' => $type])))
            ->toThrow(ValidationException::class, 'formato válido');
    })->with([
        'too short' => ['TS001', 'tractocamion'],
        'digits first' => ['123TST', 'rigido'],
        'motorcycle format on a truck' => ['TST12A', 'tractocamion'],
        'truck format on a motorcycle' => ['TST123', 'motocicleta'],
        'truck format on a semitrailer' => ['TST123', 'semirremolque'],
        'trailer with another letter' => ['T12345', 'semirremolque'],
        'symbols' => ['TST#01', 'rigido'],
    ]);

    it('reads the formats from configuration', function (): void {
        config(['integra.plates.formats.standard' => '/^[A-Z]{2}\d{4}$/']);

        expect(app(CreateVehicle::class)->handle(vehicleData(['plate' => 'TS0001']))->plate)->toBe('TS0001');
    });

    it('keeps plates unique within a company, normalised', function (): void {
        app(CreateVehicle::class)->handle(vehicleData());

        expect(fn () => app(CreateVehicle::class)->handle(vehicleData(['plate' => 'tst 001'])))
            ->toThrow(ValidationException::class, 'Ya hay un vehículo con esta placa');
    });

    it('allows the same plate in another company', function (): void {
        app(CreateVehicle::class)->handle(vehicleData());

        $other = CompanyContext::run($this->companyB, fn () => app(CreateVehicle::class)->handle(vehicleData()));

        expect($other->plate)->toBe('TST001')
            ->and($other->company_id)->toBe($this->companyB->getKey());
    });

    it('frees the plate of a deleted vehicle, and the database enforces uniqueness', function (): void {
        $vehicle = app(CreateVehicle::class)->handle(vehicleData());
        $vehicle->delete();

        expect(app(CreateVehicle::class)->handle(vehicleData())->plate)->toBe('TST001')
            ->and(Vehicle::withTrashed()->where('plate', 'TST001')->count())->toBe(2);

        Vehicle::factory()->create(['plate' => 'TST001']);
    })->throws(QueryException::class);

    it('updates a vehicle keeping its own plate', function (): void {
        $vehicle = app(CreateVehicle::class)->handle(vehicleData());

        app(UpdateVehicle::class)->handle($vehicle, vehicleData(['color' => 'Rojo', 'status' => VehicleStatus::Retired->value]));

        expect($vehicle->fresh()->color)->toBe('Rojo')
            ->and($vehicle->fresh()->status)->toBe(VehicleStatus::Retired);
    });

    it('rejects a branch of another company', function (): void {
        $branchB = CompanyContext::run($this->companyB, fn () => Branch::query()->sole());

        app(CreateVehicle::class)->handle(vehicleData(['branch_id' => $branchB->getKey()]));
    })->throws(ValidationException::class);
});

describe('isolation and permissions', function (): void {
    beforeEach(function (): void {
        $this->vehicleB = CompanyContext::run($this->companyB, fn () => Vehicle::factory()->create(['plate' => 'TST900']));
    });

    it('never finds, changes or deletes a vehicle of another company', function (): void {
        expect(Vehicle::query()->find($this->vehicleB->getKey()))->toBeNull()
            ->and(Vehicle::query()->whereKey($this->vehicleB->getKey())->update(['brand' => 'X']))->toBe(0)
            ->and(Vehicle::query()->whereKey($this->vehicleB->getKey())->delete())->toBe(0)
            ->and($this->adminA->can('view', $this->vehicleB))->toBeFalse()
            ->and($this->adminA->can('update', $this->vehicleB))->toBeFalse()
            ->and($this->adminA->can('delete', $this->vehicleB))->toBeFalse();
    });

    it('applies the role permissions', function (CompanyRole $role, bool $view, bool $write, bool $delete): void {
        $user = createMember($this->companyA, $role);
        $vehicle = Vehicle::factory()->create();

        expect($user->can('view', $vehicle))->toBe($view)
            ->and($user->can('create', Vehicle::class))->toBe($write)
            ->and($user->can('update', $vehicle))->toBe($write)
            ->and($user->can('delete', $vehicle))->toBe($delete)
            ->and($user->can('forceDelete', $vehicle))->toBeFalse();
    })->with([
        'company administrator' => [CompanyRole::CompanyAdmin, true, true, true],
        'PESV leader' => [CompanyRole::PesvLeader, true, true, false],
        'management' => [CompanyRole::Management, true, false, false],
        'area manager' => [CompanyRole::AreaManager, true, false, false],
        'read only' => [CompanyRole::Viewer, true, false, false],
        'driver' => [CompanyRole::Driver, false, false, false],
    ]);

    it('denies company vehicles to the platform administrator', function (): void {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $vehicle = Vehicle::factory()->create();

        expect($platformAdmin->can('view', $vehicle))->toBeFalse()
            ->and($platformAdmin->can('create', Vehicle::class))->toBeFalse();
    });

    it('records creation and changes in the audit log', function (): void {
        $vehicle = app(CreateVehicle::class)->handle(vehicleData());
        app(UpdateVehicle::class)->handle($vehicle, vehicleData(['status' => 'in_maintenance']));

        $entries = AuditEntry::query()->where('subject_type', 'vehicle')->where('subject_id', $vehicle->getKey())->orderBy('id')->get();

        expect($entries->pluck('event')->all())->toBe(['created', 'updated'])
            ->and($entries->last()->attribute_changes['attributes']['status'])->toBe('in_maintenance')
            ->and($entries->last()->properties['subject_label'])->toBe('TST001');
    });
});
