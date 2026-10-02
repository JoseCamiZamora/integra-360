<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Enums\ModuleAccessLevel;
use Modules\Core\Licensing\LicensedModulePolicy;
use Modules\Core\Licensing\ModuleAccess;
use Modules\Core\Models\User;
use Modules\Pesv\Filament\Pages\PesvOverview;
use Modules\Pesv\Providers\PesvServiceProvider;

/*
 * Criteria 6 and 7: without a PESV license its menu is hidden and its routes
 * answer 403; an expired license allows reading but not writing for 30 days.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2026, 10, 15, 9, 0, 0, 'America/Bogota'));
    seedCore();

    $this->company = createCompany();
    $this->leader = createMember($this->company, CompanyRole::PesvLeader);

    // A route of the licensable module, protected like modules/Pesv/routes.
    Route::middleware(['web', 'auth', 'password.changed', 'company.active', 'module.licensed:pesv'])->group(function (): void {
        Route::get('/pesv-test', fn () => 'ok');
        Route::post('/pesv-test', fn () => 'saved');
    });
});

function pesvLevel(): ModuleAccessLevel
{
    app()->forgetInstance(ModuleAccess::class);

    return app(ModuleAccess::class)->level(test()->company->fresh(), 'pesv');
}

describe('without a license (criterion 6)', function (): void {
    it('hides the PESV menu and answers 403', function (): void {
        $this->actingAs($this->leader)
            ->get('/app/'.$this->company->getKey())
            ->assertOk()
            ->assertDontSee('Plan Estratégico')
            ->assertDontSee('/app/'.$this->company->getKey().'/pesv');

        $this->actingAs($this->leader)->get('/app/'.$this->company->getKey().'/pesv')->assertForbidden();
        $this->actingAs($this->leader)->get('/pesv-test')->assertForbidden();
    });

    it('shows the menu and opens the routes with an active license', function (): void {
        createLicense($this->company);

        $this->actingAs($this->leader)
            ->get('/app/'.$this->company->getKey())
            ->assertOk()
            ->assertSee('/app/'.$this->company->getKey().'/pesv');

        $this->actingAs($this->leader)->get('/app/'.$this->company->getKey().'/pesv')->assertOk()->assertSee('Plan Estratégico de Seguridad Vial');
        $this->actingAs($this->leader)->get('/pesv-test')->assertOk();
        $this->actingAs($this->leader)->post('/pesv-test')->assertOk();
    });

    it('still requires the role permission', function (): void {
        createLicense($this->company);
        $driver = createMember($this->company, CompanyRole::Driver);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($driver);

        CompanyContext::run($this->company, fn () => expect(PesvOverview::canAccess())->toBeFalse());
    });

    it('applies the license middleware to the PESV module routes automatically', function (): void {
        $route = collect(Route::getRoutes()->getRoutes())->first(fn ($route): bool => $route->uri() === 'pesv-test');

        expect($route->gatherMiddleware())->toContain('module.licensed:pesv');

        $provider = app()->getProvider(PesvServiceProvider::class);
        $method = new ReflectionMethod($provider, 'routeMiddleware');

        expect($method->invoke($provider))->toBe(['auth', 'password.changed', 'company.active', 'module.licensed:pesv']);
    });
});

describe('license dates (criterion 7)', function (): void {
    it('computes the access level from the dates', function (?string $startsAt, ?string $endsAt, bool $active, ModuleAccessLevel $expected): void {
        createLicense($this->company, ['starts_at' => $startsAt, 'ends_at' => $endsAt, 'is_active' => $active]);

        expect(pesvLevel())->toBe($expected);
    })->with([
        'no expiry' => ['2026-01-01', null, true, ModuleAccessLevel::Full],
        'last valid day' => ['2026-01-01', '2026-10-15', true, ModuleAccessLevel::Full],
        'expired yesterday' => ['2026-01-01', '2026-10-14', true, ModuleAccessLevel::ReadOnly],
        'expired 30 days ago' => ['2026-01-01', '2026-09-15', true, ModuleAccessLevel::ReadOnly],
        'expired 31 days ago' => ['2026-01-01', '2026-09-14', true, ModuleAccessLevel::None],
        'not started yet' => ['2026-10-16', null, true, ModuleAccessLevel::None],
        'deactivated' => ['2026-01-01', null, false, ModuleAccessLevel::None],
    ]);

    it('allows reading but not writing during the 30 days after expiry', function (): void {
        createLicense($this->company, ['starts_at' => '2026-01-01', 'ends_at' => '2026-10-31']);

        $this->actingAs($this->leader)->post('/pesv-test')->assertOk();

        // Simulated dates: the license expires.
        $this->travelTo(Carbon::create(2026, 11, 10, 9, 0, 0, 'America/Bogota'));

        $this->actingAs($this->leader)->get('/pesv-test')->assertOk();
        $this->actingAs($this->leader)->get('/app/'.$this->company->getKey().'/pesv')->assertOk();
        $this->actingAs($this->leader)->post('/pesv-test')->assertForbidden();

        $this->travelTo(Carbon::create(2026, 11, 30, 9, 0, 0, 'America/Bogota'));
        $this->actingAs($this->leader)->get('/pesv-test')->assertOk();

        $this->travelTo(Carbon::create(2026, 12, 1, 9, 0, 0, 'America/Bogota'));
        $this->actingAs($this->leader)->get('/pesv-test')->assertForbidden();
        $this->actingAs($this->leader)->get('/app/'.$this->company->getKey().'/pesv')->assertForbidden();
    });

    it('makes LicensedModulePolicy read-only during the grace period', function (): void {
        createLicense($this->company, ['starts_at' => '2026-01-01', 'ends_at' => '2026-10-10']);

        $policy = new class extends LicensedModulePolicy
        {
            protected function moduleCode(): string
            {
                return 'pesv';
            }

            protected function resource(): string
            {
                return 'overview';
            }
        };

        $record = new class extends Model {};

        CompanyContext::run($this->company, function () use ($policy, $record): void {
            $leader = $this->leader->forgetCompanyRoles();

            expect($policy->viewAny($leader))->toBeTrue()
                ->and($policy->view($leader, $record))->toBeTrue()
                ->and($policy->create($leader))->toBeFalse()
                ->and($policy->update($leader, $record))->toBeFalse()
                ->and($policy->delete($leader, $record))->toBeFalse();
        });
    });

    it('never deletes data when a license expires', function (): void {
        $license = createLicense($this->company, ['starts_at' => '2025-01-01', 'ends_at' => '2025-12-31']);

        expect(pesvLevel())->toBe(ModuleAccessLevel::None)
            ->and(CompanyContext::run($this->company, fn () => $license->fresh()))->not->toBeNull();
    });
});

describe('ModuleAccess', function (): void {
    it('always enables Core', function (): void {
        expect(app(ModuleAccess::class)->level($this->company, 'core'))->toBe(ModuleAccessLevel::Full);
    });

    it('gives nothing to an inactive company or an unknown module', function (): void {
        createLicense($this->company);

        expect(app(ModuleAccess::class)->isEnabled($this->company, 'sgsst'))->toBeFalse();

        $this->company->update(['is_active' => false]);

        expect(pesvLevel())->toBe(ModuleAccessLevel::None);
    });

    it('reports the license limits', function (): void {
        $access = app(ModuleAccess::class);

        expect($access->limits($this->company, 'pesv')->allowsAnotherVehicle(0))->toBeFalse();

        createLicense($this->company, ['max_vehicles' => 6, 'max_people' => null]);
        app()->forgetInstance(ModuleAccess::class);
        $limits = app(ModuleAccess::class)->limits($this->company, 'pesv');

        expect($limits->maxVehicles)->toBe(6)
            ->and($limits->allowsAnotherVehicle(5))->toBeTrue()
            ->and($limits->allowsAnotherVehicle(6))->toBeFalse()
            ->and($limits->maxPeople)->toBeNull()
            ->and($limits->allowsAnotherPerson(1000))->toBeTrue();
    });

    it('lists the companies whose scheduled tasks must run', function (): void {
        $other = createCompany();
        $expired = createCompany();
        createLicense($this->company);
        createLicense($expired, ['starts_at' => '2026-01-01', 'ends_at' => '2026-10-01']);

        $companies = app(ModuleAccess::class)->companiesWithWriteAccess('pesv');

        expect($companies->pluck('id')->all())->toBe([$this->company->getKey()])
            ->and($other)->not->toBeNull();
    });

    it('answers None without an active company', function (): void {
        expect(app(ModuleAccess::class)->currentLevel('pesv'))->toBe(ModuleAccessLevel::None);
    });
});

it('keeps unlicensed users of the platform out of company modules', function (): void {
    createLicense($this->company);
    $admin = User::factory()->platformAdmin()->create();

    $this->actingAs($admin)->get('/pesv-test')->assertRedirect(route('company.choose'));
});
