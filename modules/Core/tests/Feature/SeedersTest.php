<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\DocumentCompliance;
use Modules\Core\Database\Seeders\DocumentTypeCatalogSeeder;
use Modules\Core\Database\Seeders\LicenseCategoryEquivalenceSeeder;
use Modules\Core\Enums\ModuleAccessLevel;
use Modules\Core\Licensing\ModuleAccess;
use Modules\Core\Models\Company;
use Modules\Core\Models\DocumentType;
use Modules\Core\Models\Driver;
use Modules\Core\Models\ExpiringDocument;
use Modules\Core\Models\Module;
use Modules\Core\Models\Person;
use Modules\Core\Models\User;
use Modules\Core\Models\Vehicle;
use Modules\Core\Models\VehicleAssignment;
use Modules\Core\Models\VehicleTypeLicenseCategory;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('seeds fictitious development data', function (): void {
    $this->seed(DatabaseSeeder::class);

    $companies = Company::query()->orderBy('legal_name')->get();

    expect($companies)->toHaveCount(2)
        ->and(User::query()->where('is_platform_admin', true)->count())->toBe(1)
        ->and(Module::query()->pluck('code')->all())->toContain('core', 'pesv', 'sgsst', 'human-resources', 'sagrilaft', 'quality');

    foreach ($companies as $company) {
        $roles = $company->users->flatMap(fn (User $user): array => $user->roleNamesInCompany($company))->sort()->values()->all();

        expect($roles)->toBe(['area_manager', 'company_admin', 'driver', 'management', 'pesv_leader', 'viewer'])
            ->and(CompanyContext::run($company, fn () => $company->branches()->where('is_main', true)->count()))->toBe(1);
    }

    $levels = $companies->map(fn (Company $company): ModuleAccessLevel => app(ModuleAccess::class)->level($company, 'pesv'));

    expect($levels->filter(fn (ModuleAccessLevel $level): bool => $level === ModuleAccessLevel::Full))->toHaveCount(1);
});

it('seeds the cargo company operation with fictitious data in varied states', function (): void {
    $this->seed(DatabaseSeeder::class);

    $cargo = Company::query()->where('trade_name', 'Cordillera Carga')->sole();

    CompanyContext::run($cargo, function (): void {
        $vehicles = Vehicle::query()->get();
        $types = $vehicles->countBy(fn (Vehicle $vehicle): string => $vehicle->vehicle_type->value)->all();

        expect($vehicles)->toHaveCount(6)
            ->and($vehicles->every(fn (Vehicle $vehicle): bool => str_starts_with($vehicle->plate, 'TST')))->toBeTrue()
            ->and($types)->toEqual(['tractocamion' => 3, 'rigido' => 2, 'volqueta' => 1])
            ->and(Person::query()->count())->toBe(8)
            ->and(Driver::query()->count())->toBe(6)
            ->and(VehicleAssignment::query()->current()->count())->toBe(5);

        $statuses = ExpiringDocument::query()->with('documentType')->get()
            ->map(fn (ExpiringDocument $document): string => $document->status()->value)
            ->unique()->sort()->values()->all();

        expect($statuses)->toBe(['expired', 'expiring_soon', 'no_expiry', 'valid']);

        $reports = app(DocumentCompliance::class)->forVehicles(Vehicle::query()->get());

        expect(collect($reports)->filter(fn ($report): bool => $report->missing !== [])->count())->toBeGreaterThan(0)
            ->and(collect($reports)->filter(fn ($report): bool => $report->isCompliant())->count())->toBeGreaterThan(0);

        // Licenses: Andrés expiring soon, Beatriz expired, Diana missing.
        $people = Person::query()->get()->keyBy('document_number');
        $compliance = app(DocumentCompliance::class);

        expect($compliance->forPerson($people['1010000070'])->expiringSoon[0]->documentType->code)->toBe('person.driving_license')
            ->and($compliance->forPerson($people['1010000080'])->expired[0]->documentType->code)->toBe('person.driving_license')
            ->and($compliance->forPerson($people['1010000100'])->missing[0]->code)->toBe('person.driving_license')
            ->and($compliance->forPerson($people['1010000050'])->isCompliant())->toBeTrue();

        // The development driver account sees TST001 in /conductor.
        $luis = Person::query()->where('document_number', '1010000050')->sole();

        expect($luis->user_id)->not->toBeNull()
            ->and($luis->driver?->currentAssignment?->vehicle->plate)->toBe('TST001');
    });
});

it('seeds production with catalogue, roles, permissions and the platform administrator only', function (): void {
    config(['integra.platform_admin' => [
        'name' => 'Dueña de la Plataforma',
        'document_type' => 'CC',
        'document_number' => '79000111',
        'email' => 'duena@ejemplo.test',
        'password' => 'clave-temporal-larga',
    ]]);

    $this->seed(ProductionSeeder::class);
    $this->seed(ProductionSeeder::class);

    $admin = User::query()->sole();

    expect($admin->is_platform_admin)->toBeTrue()
        ->and($admin->must_change_password)->toBeTrue()
        ->and(Company::query()->count())->toBe(0)
        ->and(Role::query()->count())->toBe(6)
        ->and(Module::query()->where('code', 'pesv')->value('is_available'))->toBeTrue()
        ->and(Module::query()->where('code', 'quality')->value('is_available'))->toBeFalse()
        ->and(DocumentType::query()->globalOnly()->count())->toBe(count(DocumentTypeCatalogSeeder::TYPES))
        ->and(VehicleTypeLicenseCategory::query()->count())->toBe(count(array_merge(...array_values(LicenseCategoryEquivalenceSeeder::CATEGORIES))))
        ->and(Vehicle::withoutGlobalScopes()->count())->toBe(0)
        ->and(Person::withoutGlobalScopes()->count())->toBe(0);
});

it('skips the platform administrator when its variables are missing', function (): void {
    config(['integra.platform_admin' => ['name' => null, 'document_type' => 'CC', 'document_number' => null, 'email' => null, 'password' => null]]);

    $this->seed(ProductionSeeder::class);

    expect(User::query()->count())->toBe(0);
});
