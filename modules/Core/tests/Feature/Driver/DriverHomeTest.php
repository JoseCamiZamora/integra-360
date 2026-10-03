<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Actions\AssignVehicle;
use Modules\Core\Actions\CreatePersonAccess;
use Modules\Core\Database\Seeders\DocumentTypeCatalogSeeder;
use Modules\Core\Database\Seeders\LicenseCategoryEquivalenceSeeder;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Enums\VehicleType;
use Modules\Core\Models\DocumentType;
use Modules\Core\Models\Driver;
use Modules\Core\Models\ExpiringDocument;
use Modules\Core\Models\Person;
use Modules\Core\Models\User;
use Modules\Core\Models\Vehicle;

/*
 * Criterion 11: a driver sees their assigned vehicle and the document
 * status of the vehicle and their own in /conductor; without an assignment,
 * a clear message. The inspection button stays disabled until I360-05.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedCore();
    app(DocumentTypeCatalogSeeder::class)->run();
    app(LicenseCategoryEquivalenceSeeder::class)->run();

    $this->company = createCompany();
    $admin = createMember($this->company, CompanyRole::CompanyAdmin);

    [$this->user, $this->vehicle, $this->person] = CompanyContext::run($this->company, function () use ($admin): array {
        $this->actingAs($admin);

        $person = Person::factory()->create(['first_name' => 'Luis', 'last_name' => 'Prueba']);
        $driver = Driver::factory()->for($person)->create();
        $user = app(CreatePersonAccess::class)->handle($person)->user;
        $user->forceFill(['must_change_password' => false])->save();

        $vehicle = Vehicle::factory()->ofType(VehicleType::Tractocamion)->create([
            'plate' => 'TST001',
            'brand' => 'Marca de prueba',
            'model_line' => 'Línea 40',
        ]);

        // SOAT expired yesterday; the rest of the vehicle documents are missing.
        ExpiringDocument::factory()->of($vehicle, DocumentType::query()->where('code', 'vehicle.soat')->sole())
            ->expiringOn(now()->subDay()->toDateString())->create();

        app(AssignVehicle::class)->handle($vehicle, $driver);

        return [$user, $vehicle, $person];
    });
});

it('shows the assigned vehicle and both document statuses', function (): void {
    $this->actingAs($this->user)
        ->get('/conductor')
        ->assertOk()
        ->assertSee('Tu vehículo')
        ->assertSee('TST001')
        ->assertSee('Tractocamión · Marca de prueba Línea 40')
        ->assertSee('Documentos del vehículo')
        ->assertSee('Mis documentos')
        ->assertSee('No cumple')
        ->assertSee('SOAT: vencido')
        ->assertSee('Licencia de conducción: no registrado')
        ->assertDontSee('Aún no tienes un vehículo asignado');
});

it('shows the status, never document numbers or files', function (): void {
    $html = $this->actingAs($this->user)->get('/conductor')->getContent();

    $number = CompanyContext::run($this->company, fn () => ExpiringDocument::query()->value('number'));

    expect($html)->not->toContain((string) $number)
        ->not->toContain('documentos/archivos');
});

it('shows a clear message without an assigned vehicle', function (): void {
    CompanyContext::run($this->company, fn () => $this->vehicle->currentAssignment->update(['ends_at' => now()]));

    $this->actingAs($this->user)
        ->get('/conductor')
        ->assertOk()
        ->assertSee('Aún no tienes un vehículo asignado. Avisa a tu administrador.')
        ->assertDontSee('TST001');
});

it('works for a driver account without a person record', function (): void {
    $driverOnly = createMember($this->company, CompanyRole::Driver, ['must_change_password' => false]);

    $this->actingAs($driverOnly)
        ->get('/conductor')
        ->assertOk()
        ->assertSee('Aún no tienes un vehículo asignado');
});

it('keeps the inspection button disabled, with an explanation and the touch sizes', function (): void {
    $html = (string) $this->actingAs($this->user)->get('/conductor')->getContent();

    expect($html)->toContain('Iniciar inspección')
        ->toMatch('/<button[^>]*class="btn-primary[^"]*"[^>]*disabled/')
        ->toContain('La inspección preoperacional estará disponible cuando se active el módulo PESV.');
});

it('never shows another company vehicle', function (): void {
    $other = createCompany();
    CompanyContext::run($other, fn () => Vehicle::factory()->create(['plate' => 'TST900']));

    $this->actingAs($this->user)->get('/conductor')->assertDontSee('TST900');
});

it('ships no JavaScript besides Livewire with the vehicle card', function (): void {
    $html = (string) $this->actingAs($this->user)->get('/conductor')->getContent();

    preg_match_all('/<script[^>]*src="([^"]+)"/', $html, $scripts);

    foreach ($scripts[1] as $src) {
        expect($src)->toContain('livewire');
    }
});

it('does not show the driver view to the platform administrator', function (): void {
    // No company of their own: company.active sends them away before the page.
    $this->actingAs(User::factory()->create(['is_platform_admin' => true]))->get('/conductor')->assertRedirect();
});
