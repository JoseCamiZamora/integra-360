<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Enums\ModuleAccessLevel;
use Modules\Core\Licensing\ModuleAccess;
use Modules\Core\Models\Company;
use Modules\Core\Models\Module;
use Modules\Core\Models\User;
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
        ->and(Module::query()->where('code', 'quality')->value('is_available'))->toBeFalse();
});

it('skips the platform administrator when its variables are missing', function (): void {
    config(['integra.platform_admin' => ['name' => null, 'document_type' => 'CC', 'document_number' => null, 'email' => null, 'password' => null]]);

    $this->seed(ProductionSeeder::class);

    expect(User::query()->count())->toBe(0);
});
