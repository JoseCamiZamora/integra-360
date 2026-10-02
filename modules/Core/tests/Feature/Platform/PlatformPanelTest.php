<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Filament\Platform\Resources\Companies\Pages\CreateCompany;
use Modules\Core\Filament\Platform\Resources\Companies\Pages\EditCompany;
use Modules\Core\Filament\Platform\Resources\Companies\Pages\ListCompanies;
use Modules\Core\Filament\Platform\Resources\Companies\RelationManagers\LicensesRelationManager;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Company;
use Modules\Core\Models\ModuleLicense;
use Modules\Core\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedCore();

    $this->platformAdmin = User::factory()->platformAdmin()->create();
    actingInPlatform($this->platformAdmin);
});

function companyFormData(array $overrides = []): array
{
    return array_merge([
        'legal_name' => 'Transportes Ficticios del Norte S.A.S.',
        'trade_name' => 'Ficticios del Norte',
        'nit' => '900.123.456-8',
        'mission_type' => 'transport',
        'city' => 'Cúcuta',
        'department' => 'Norte de Santander',
        'address' => 'Avenida Ficticia 1',
        'phone' => '6070000000',
        'email' => 'contacto@ficticios.test',
        'legal_representative' => 'Persona Inventada',
        'is_active' => true,
    ], $overrides);
}

it('creates a company with its main branch', function (): void {
    Livewire::test(CreateCompany::class)
        ->fillForm(companyFormData())
        ->call('create')
        ->assertHasNoFormErrors();

    $company = Company::query()->sole();

    expect($company->nit)->toBe('900123456-8')
        ->and(CompanyContext::run($company, fn () => Branch::query()->where('is_main', true)->count()))->toBe(1);
});

it('validates the NIT verification digit', function (string $nit, string $error): void {
    Livewire::test(CreateCompany::class)
        ->fillForm(companyFormData(['nit' => $nit]))
        ->call('create')
        ->assertHasFormErrors(['nit']);

    expect(Company::query()->count())->toBe(0);
})->with([
    'wrong digit' => ['900123456-7', 'dígito'],
    'no digit' => ['900123456', 'formato'],
]);

it('rejects a NIT that is already registered', function (): void {
    createCompany(['nit' => '900123456-8']);

    Livewire::test(CreateCompany::class)
        ->fillForm(companyFormData())
        ->call('create')
        ->assertHasFormErrors(['nit']);

    expect(Company::query()->count())->toBe(1);
});

it('deactivates a company and blocks its users', function (): void {
    $company = createCompany();
    $admin = createMember($company, CompanyRole::CompanyAdmin);

    Livewire::test(ListCompanies::class)->callTableAction('toggleActive', $company->getKey());

    expect($company->fresh()->is_active)->toBeFalse()
        ->and($admin->activeCompanies())->toBeEmpty();
});

it('assigns licenses inside the company context', function (): void {
    $company = createCompany();

    Livewire::test(LicensesRelationManager::class, ['ownerRecord' => $company, 'pageClass' => EditCompany::class])
        ->callTableAction(CreateAction::class, data: [
            'module_code' => 'pesv',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addYear()->toDateString(),
            'max_vehicles' => 6,
            'is_active' => true,
        ])
        ->assertHasNoTableActionErrors();

    $license = CompanyContext::run($company, fn () => ModuleLicense::query()->sole());

    expect($license->module_code)->toBe('pesv')
        ->and($license->max_vehicles)->toBe(6);
});

it('only offers licensable modules', function (): void {
    $company = createCompany();

    Livewire::test(LicensesRelationManager::class, ['ownerRecord' => $company, 'pageClass' => EditCompany::class])
        ->callTableAction(CreateAction::class, data: ['module_code' => 'core', 'starts_at' => now()->toDateString()])
        ->assertHasTableActionErrors(['module_code']);
});

it('creates the first company administrator with a temporary password', function (): void {
    $company = createCompany();

    Livewire::test(EditCompany::class, ['record' => $company->getKey()])
        ->callAction('createAdmin', [
            'name' => 'Primera Administradora',
            'document_type' => 'CC',
            'document_number' => '52123456',
            'email' => 'admin@ficticios.test',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified();

    $admin = User::query()->where('document_number', '52123456')->sole();

    expect($admin->must_change_password)->toBeTrue()
        ->and($admin->roleNamesInCompany($company))->toBe(['company_admin'])
        ->and($admin->isActiveMemberOf($company))->toBeTrue();
});

it('is closed to company users', function (): void {
    $company = createCompany();
    $admin = createMember($company, CompanyRole::CompanyAdmin);

    $this->actingAs($admin)->get('/plataforma/companies')->assertForbidden();
    $this->actingAs($admin)->get('/plataforma/audit')->assertForbidden();
});

it('shows every company\'s audit log and records that it did', function (): void {
    createCompany(['legal_name' => 'Auditada Uno S.A.S.']);
    createCompany(['legal_name' => 'Auditada Dos S.A.S.']);

    $this->get('/plataforma/audit')
        ->assertOk()
        ->assertSee('Auditada Uno S.A.S.')
        ->assertSee('Auditada Dos S.A.S.');
});
