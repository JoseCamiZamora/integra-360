<?php

declare(strict_types=1);

use App\Modules\ModuleRegistry;
use App\Modules\ModuleServiceProvider;
use App\Support\Tenancy\CompanyContext;
use Filament\Facades\Filament;
use Modules\Core\Actions\CreateCompany;
use Modules\Core\Actions\SyncPermissions;
use Modules\Core\Actions\SyncUserRoles;
use Modules\Core\Database\Factories\CompanyFactory;
use Modules\Core\Database\Seeders\ModuleCatalogSeeder;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Models\Company;
use Modules\Core\Models\Membership;
use Modules\Core\Models\ModuleLicense;
use Modules\Core\Models\User;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests (tests/Feature) and every module's tests (modules/*\/tests)
| run on the Laravel TestCase against the MySQL testing database
| (integra360_testing, see phpunit.xml). Tests that touch the database
| must use RefreshDatabase explicitly.
|
*/

pest()->extend(TestCase::class)
    ->in('Feature', '../modules/*/tests');

/*
|--------------------------------------------------------------------------
| Helpers (fictitious data only)
|--------------------------------------------------------------------------
*/

/**
 * Module catalogue, roles and permissions, as in production.
 */
function seedCore(): void
{
    app(ModuleCatalogSeeder::class)->run(app(ModuleRegistry::class));
    app(SyncPermissions::class)->handle();
}

/**
 * A company with its main branch (through the real action).
 *
 * @param  array<string, mixed>  $attributes
 */
function createCompany(array $attributes = []): Company
{
    return app(CreateCompany::class)->handle(array_merge(CompanyFactory::new()->raw(), $attributes));
}

/**
 * A user with an active membership and the given roles in $company.
 *
 * @param  CompanyRole|list<CompanyRole>  $roles
 * @param  array<string, mixed>  $attributes
 */
function createMember(Company $company, CompanyRole|array $roles, array $attributes = [], bool $active = true): User
{
    $user = User::factory()->create($attributes);

    addMembership($user, $company, $roles, $active);

    return $user;
}

/**
 * @param  CompanyRole|list<CompanyRole>  $roles
 */
function addMembership(User $user, Company $company, CompanyRole|array $roles, bool $active = true): void
{
    $roles = array_map(fn (CompanyRole $role): string => $role->value, is_array($roles) ? $roles : [$roles]);

    CompanyContext::run($company, function () use ($user, $roles, $active): void {
        Membership::query()->create(['user_id' => $user->getKey(), 'is_active' => $active, 'joined_at' => now()]);
        app(SyncUserRoles::class)->handle($user, $roles);
    });
}

/**
 * @param  array<string, mixed>  $attributes
 */
function createLicense(Company $company, array $attributes = []): ModuleLicense
{
    return CompanyContext::run($company, fn (): ModuleLicense => ModuleLicense::factory()->create($attributes));
}

/**
 * Prepares Livewire tests of the company panel: signed in, inside $company.
 */
function actingInPanel(User $user, Company $company): void
{
    test()->actingAs($user);

    Filament::setCurrentPanel(Filament::getPanel(ModuleServiceProvider::ADMIN_PANEL_ID));
    Filament::setTenant($company);
    CompanyContext::activate($company);

    // Each Livewire test update is a request that clears the context when it
    // ends; in the browser the persistent tenant middleware sets it again.
    app()->terminating(fn () => CompanyContext::activate($company));
}

/**
 * Prepares Livewire tests of the platform panel.
 */
function actingInPlatform(User $user): void
{
    test()->actingAs($user);

    Filament::setCurrentPanel(Filament::getPanel(User::PLATFORM_PANEL_ID));
}
