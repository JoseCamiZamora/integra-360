<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use App\Support\Tenancy\MissingCompanyContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Actions\AssignVehicle;
use Modules\Core\Actions\CreatePersonAccess;
use Modules\Core\Actions\DeleteVehicle;
use Modules\Core\Actions\EndVehicleAssignment;
use Modules\Core\Actions\RegisterExpiringDocument;
use Modules\Core\Actions\SetPersonStatus;
use Modules\Core\Actions\SyncDriverProfile;
use Modules\Core\Actions\UpdateVehicle;
use Modules\Core\Database\Seeders\DocumentTypeCatalogSeeder;
use Modules\Core\Database\Seeders\LicenseCategoryEquivalenceSeeder;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Enums\LicenseCategory;
use Modules\Core\Enums\PersonStatus;
use Modules\Core\Enums\VehicleType;
use Modules\Core\Models\AuditEntry;
use Modules\Core\Models\DocumentType;
use Modules\Core\Models\Driver;
use Modules\Core\Models\Person;
use Modules\Core\Models\User;
use Modules\Core\Models\Vehicle;
use Modules\Core\Models\VehicleAssignment;
use Modules\Core\Models\VehicleTypeLicenseCategory;

/*
 * Criteria 3 (one current assignment per side, history kept), 4 (license
 * category warns, never blocks), 12 (audit) and 1 for assignments.
 */

uses(RefreshDatabase::class);

function makeDriver(string $category = 'C3', array $person = []): Driver
{
    return Driver::factory()
        ->for(Person::factory()->state($person))
        ->create(['license_category' => $category]);
}

beforeEach(function (): void {
    seedCore();
    app(LicenseCategoryEquivalenceSeeder::class)->run();

    $this->company = createCompany();
    $this->admin = createMember($this->company, CompanyRole::CompanyAdmin);
    $this->actingAs($this->admin);
    CompanyContext::activate($this->company);

    $this->truck = Vehicle::factory()->ofType(VehicleType::Tractocamion)->create(['plate' => 'TST001']);
    $this->dumper = Vehicle::factory()->ofType(VehicleType::Volqueta)->create(['plate' => 'TST002']);
    $this->ana = makeDriver('C3', ['first_name' => 'Ana', 'last_name' => 'Prueba']);
    $this->beto = makeDriver('C3', ['first_name' => 'Beto', 'last_name' => 'Prueba']);

    $this->assign = app(AssignVehicle::class);
});

afterEach(fn () => CompanyContext::forget());

describe('one current assignment per vehicle and per driver', function (): void {
    it('assigns a vehicle to a driver', function (): void {
        $result = $this->assign->handle($this->truck, $this->ana, notes: 'Ruta Bogotá - Funza');

        expect($result->assignment->isCurrent())->toBeTrue()
            ->and($result->closed)->toBe([])
            ->and($result->licenseWarning)->toBeNull()
            ->and($this->truck->currentAssignment->driver_id)->toBe($this->ana->getKey())
            ->and($this->ana->currentAssignment->vehicle_id)->toBe($this->truck->getKey());
    });

    it('closes the previous driver of the vehicle and keeps the history', function (): void {
        $first = $this->assign->handle($this->truck, $this->ana)->assignment;
        $this->travel(1)->days();
        $result = $this->assign->handle($this->truck, $this->beto);

        expect($result->closed)->toHaveCount(1)
            ->and($first->fresh()->ends_at)->not->toBeNull()
            ->and($first->fresh()->ends_at->equalTo($result->assignment->starts_at))->toBeTrue()
            ->and($this->truck->assignments()->count())->toBe(2)
            ->and($this->truck->assignments()->current()->sole()->driver_id)->toBe($this->beto->getKey())
            ->and($this->ana->fresh()->currentAssignment)->toBeNull();
    });

    it('closes the previous vehicle of the driver', function (): void {
        $this->assign->handle($this->truck, $this->ana);
        $result = $this->assign->handle($this->dumper, $this->ana);

        expect($result->closed[0]->vehicle_id)->toBe($this->truck->getKey())
            ->and($this->truck->fresh()->currentAssignment)->toBeNull()
            ->and($this->ana->assignments()->count())->toBe(2)
            ->and($this->ana->assignments()->current()->sole()->vehicle_id)->toBe($this->dumper->getKey());
    });

    it('closes both sides when swapping', function (): void {
        $this->assign->handle($this->truck, $this->ana);
        $this->assign->handle($this->dumper, $this->beto);

        $result = $this->assign->handle($this->truck, $this->beto);

        expect($result->closed)->toHaveCount(2)
            ->and(VehicleAssignment::query()->current()->count())->toBe(1)
            ->and(VehicleAssignment::query()->count())->toBe(3);
    });

    it('does nothing when the assignment already exists', function (): void {
        $first = $this->assign->handle($this->truck, $this->ana)->assignment;
        $again = $this->assign->handle($this->truck, $this->ana);

        expect($again->assignment->is($first))->toBeTrue()
            ->and(VehicleAssignment::query()->count())->toBe(1);
    });

    it('enforces it in the database', function (): void {
        VehicleAssignment::query()->create(['vehicle_id' => $this->truck->getKey(), 'driver_id' => $this->ana->getKey(), 'starts_at' => now()]);
        VehicleAssignment::query()->create(['vehicle_id' => $this->truck->getKey(), 'driver_id' => $this->beto->getKey(), 'starts_at' => now()]);
    })->throws(QueryException::class);

    it('ends an assignment without deleting it', function (): void {
        $assignment = $this->assign->handle($this->truck, $this->ana)->assignment;

        app(EndVehicleAssignment::class)->handle($assignment);

        expect($assignment->fresh()->isCurrent())->toBeFalse()
            ->and(VehicleAssignment::query()->count())->toBe(1);
    });
});

describe('who can be assigned', function (): void {
    it('refuses retired or deleted vehicles', function (): void {
        $this->truck->update(['status' => 'retired']);

        expect(fn () => $this->assign->handle($this->truck, $this->ana))->toThrow(ValidationException::class, 'retirado');

        $this->dumper->delete();

        expect(fn () => $this->assign->handle($this->dumper, $this->ana))->toThrow(ValidationException::class);
    });

    it('refuses people without a driver profile or retired', function (): void {
        app(SyncDriverProfile::class)->handle($this->beto->person, null);

        expect(fn () => $this->assign->handle($this->truck, $this->beto->fresh()))->toThrow(ValidationException::class, 'perfil de conductor');

        app(SetPersonStatus::class)->handle($this->ana->person, PersonStatus::Inactive);

        expect(fn () => $this->assign->handle($this->truck, $this->ana->fresh()))->toThrow(ValidationException::class, 'retirada');
    });

    it('closes the current assignment when the vehicle is retired or deleted', function (): void {
        $this->assign->handle($this->truck, $this->ana);
        app(UpdateVehicle::class)->handle($this->truck, [...$this->truck->only(['plate', 'brand', 'model_line', 'model_year']), 'vehicle_type' => 'tractocamion', 'ownership' => 'own', 'service_type' => 'cargo', 'status' => 'retired']);

        expect($this->truck->fresh()->currentAssignment)->toBeNull();

        $this->assign->handle($this->dumper, $this->beto);
        app(DeleteVehicle::class)->handle($this->dumper);

        expect(VehicleAssignment::query()->current()->count())->toBe(0)
            ->and(Vehicle::withTrashed()->find($this->dumper->getKey())->trashed())->toBeTrue();
    });

    it('closes the current assignment when the person leaves or stops driving', function (): void {
        $this->assign->handle($this->truck, $this->ana);
        $this->assign->handle($this->dumper, $this->beto);

        app(SetPersonStatus::class)->handle($this->ana->person, PersonStatus::Inactive);
        app(SyncDriverProfile::class)->handle($this->beto->person, null);

        expect(VehicleAssignment::query()->current()->count())->toBe(0)
            ->and(VehicleAssignment::query()->count())->toBe(2);
    });
});

describe('license category', function (): void {
    it('warns but assigns when the category is not configured for the vehicle type', function (): void {
        $carla = makeDriver('C1');

        $result = $this->assign->handle($this->truck, $carla);

        expect($result->assignment->exists)->toBeTrue()
            ->and($result->assignment->isCurrent())->toBeTrue()
            ->and($result->licenseWarning)->toContain('C1')->toContain('Tractocamión')->toContain('B3, C3');
    });

    it('reads the equivalences from the editable table, not from code', function (): void {
        $carla = makeDriver('C1');

        VehicleTypeLicenseCategory::query()->create(['vehicle_type' => 'tractocamion', 'license_category' => 'C1']);

        expect($this->assign->handle($this->truck, $carla)->licenseWarning)->toBeNull();

        VehicleTypeLicenseCategory::query()->where('vehicle_type', 'volqueta')->where('license_category', 'C3')->delete();

        expect($this->assign->handle($this->dumper, $this->ana)->licenseWarning)->not->toBeNull();
    });

    it('seeds the initial equivalences once', function (): void {
        VehicleTypeLicenseCategory::query()->where('vehicle_type', 'motocicleta')->delete();

        app(LicenseCategoryEquivalenceSeeder::class)->run();

        expect(VehicleTypeLicenseCategory::allowedFor(VehicleType::Motocicleta))->toBe([])
            ->and(VehicleTypeLicenseCategory::allowedFor(VehicleType::Tractocamion))->toBe([LicenseCategory::B3, LicenseCategory::C3]);
    });
});

describe('audit and permissions', function (): void {
    it('records every assignment and closing', function (): void {
        $first = $this->assign->handle($this->truck, $this->ana)->assignment;
        $this->assign->handle($this->truck, $this->beto);

        $entries = AuditEntry::query()->where('subject_type', VehicleAssignment::class)->orderBy('id')->get();

        expect($entries->pluck('event')->all())->toBe(['created', 'updated', 'created'])
            ->and($entries[1]->subject_id)->toBe($first->getKey())
            ->and($entries[1]->attribute_changes['attributes'])->toHaveKey('ends_at')
            ->and($entries[0]->properties['subject_label'])->toBe('TST001 · Ana Prueba')
            ->and($entries[0]->actor?->is($this->admin))->toBeTrue();
    });

    it('applies the role permissions and lets drivers see only their own', function (CompanyRole $role, bool $view, bool $write): void {
        $user = createMember($this->company, $role);
        $assignment = $this->assign->handle($this->truck, $this->ana)->assignment;

        expect($user->can('view', $assignment))->toBe($view)
            ->and($user->can('create', VehicleAssignment::class))->toBe($write)
            ->and($user->can('end', $assignment))->toBe($write)
            ->and($user->can('delete', $assignment))->toBeFalse();
    })->with([
        'company administrator' => [CompanyRole::CompanyAdmin, true, true],
        'PESV leader' => [CompanyRole::PesvLeader, true, true],
        'management' => [CompanyRole::Management, true, false],
        'area manager' => [CompanyRole::AreaManager, true, false],
        'read only' => [CompanyRole::Viewer, true, false],
        'driver (not theirs)' => [CompanyRole::Driver, false, false],
    ]);

    it('lets a driver view their own assignment', function (): void {
        $user = app(CreatePersonAccess::class)->handle($this->ana->person)->user;
        $own = $this->assign->handle($this->truck, $this->ana)->assignment;
        $other = $this->assign->handle($this->dumper, $this->beto)->assignment;

        expect($user->can('view', $own))->toBeTrue()
            ->and($user->can('view', $other))->toBeFalse();
    });

    it('denies assignments to the platform administrator', function (): void {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

        expect($platformAdmin->can('view', $this->assign->handle($this->truck, $this->ana)->assignment))->toBeFalse()
            ->and($platformAdmin->can('create', VehicleAssignment::class))->toBeFalse();
    });
});

describe('isolation between companies', function (): void {
    beforeEach(function (): void {
        $this->companyB = createCompany();

        [$this->vehicleB, $this->driverB, $this->assignmentB] = CompanyContext::run($this->companyB, function (): array {
            $vehicle = Vehicle::factory()->create(['plate' => 'TST900']);
            $driver = makeDriver();

            return [$vehicle, $driver, app(AssignVehicle::class)->handle($vehicle, $driver)->assignment];
        });
    });

    it('never finds or ends an assignment of another company', function (): void {
        expect(VehicleAssignment::query()->find($this->assignmentB->getKey()))->toBeNull()
            ->and(VehicleAssignment::query()->whereKey($this->assignmentB->getKey())->update(['ends_at' => now()]))->toBe(0)
            ->and($this->admin->can('view', $this->assignmentB))->toBeFalse()
            ->and($this->admin->can('end', $this->assignmentB))->toBeFalse();
    });

    it('refuses to mix a vehicle or driver of another company, even loaded by other means', function (): void {
        expect(fn () => $this->assign->handle($this->truck, $this->driverB))->toThrow(MissingCompanyContext::class)
            ->and(fn () => $this->assign->handle($this->vehicleB, $this->ana))->toThrow(MissingCompanyContext::class);

        expect(VehicleAssignment::query()->count())->toBe(0);
    });

    it('refuses documents or accesses for entities of another company', function (): void {
        CompanyContext::forget();
        app(DocumentTypeCatalogSeeder::class)->run();
        CompanyContext::activate($this->company);

        $soat = DocumentType::query()->where('code', 'vehicle.soat')->sole();

        expect(fn () => app(RegisterExpiringDocument::class)->handle($this->vehicleB, $soat, ['expires_at' => '2027-01-01']))
            ->toThrow(MissingCompanyContext::class)
            ->and(fn () => app(CreatePersonAccess::class)->handle($this->driverB->person))
            ->toThrow(MissingCompanyContext::class);
    });
});
