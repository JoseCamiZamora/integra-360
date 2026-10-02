<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Models\User;
use Symfony\Component\Finder\Finder;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2026, 10, 1, 8, 30, 0, 'America/Bogota'));
    seedCore();

    $this->company = createCompany(['trade_name' => 'Transportes Prueba']);
});

it('sends guests from the home page to the sign-in screen', function (): void {
    $this->get('/')->assertRedirect('/ingreso');
    $this->get('/ingreso')->assertOk()->assertSee('Ingresa a Integra 360');
});

it('requires a session for /app, /conductor and /plataforma', function (string $uri): void {
    $this->get($uri)->assertRedirect('/ingreso');
})->with(['/app', '/conductor', '/plataforma']);

it('shows the company panel home in Spanish to panel roles', function (): void {
    $admin = createMember($this->company, CompanyRole::CompanyAdmin);

    $this->actingAs($admin)
        ->get('/app/'.$this->company->getKey())
        ->assertOk()
        ->assertSee('Integra 360')
        ->assertSee('Estado de la operación')
        ->assertSee('Jueves, 1 de octubre de 2026')
        ->assertSee('Inicio');
});

it('shows the mobile driver view to drivers', function (): void {
    $driver = createMember($this->company, CompanyRole::Driver, ['name' => 'Luis Pérez']);

    $this->actingAs($driver)
        ->get('/conductor')
        ->assertOk()
        ->assertSee('<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">', escape: false)
        ->assertSee('¡Hola, Luis!')
        ->assertSee('Jueves, 1 de octubre')
        ->assertSee('Aquí verás tu vehículo asignado');
});

it('ships no JavaScript in the driver view besides Livewire', function (): void {
    $driver = createMember($this->company, CompanyRole::Driver);

    $html = $this->actingAs($driver)->get('/conductor')->getContent();

    preg_match_all('/<script[^>]*src="([^"]+)"/', (string) $html, $scripts);

    foreach ($scripts[1] as $src) {
        expect($src)->toContain('livewire');
    }
});

it('keeps each role in its own interface', function (): void {
    $driver = createMember($this->company, CompanyRole::Driver);
    $viewer = createMember($this->company, CompanyRole::Viewer);

    $this->actingAs($driver)->get('/app/'.$this->company->getKey())->assertForbidden();
    $this->actingAs($driver)->get('/plataforma')->assertForbidden();
    $this->actingAs($viewer)->get('/conductor')->assertForbidden();
    $this->actingAs($viewer)->get('/plataforma')->assertForbidden();
});

it('opens the platform panel only to the platform administrator', function (): void {
    $admin = User::factory()->platformAdmin()->create();

    $this->actingAs($admin)->get('/plataforma/companies')->assertOk()->assertSee('Empresas');
    $this->actingAs($admin)->get('/app')->assertForbidden();
});

it('no longer has the provisional access switch anywhere', function (): void {
    $files = Finder::create()
        ->files()
        ->in(base_path())
        ->exclude(['vendor', 'node_modules', 'storage', 'bootstrap/cache', 'public', '.git', 'docs/prompts', 'tests'])
        ->notName('.env')
        ->ignoreDotFiles(false)
        ->contains('PROVISIONAL_ACCESS');

    expect(iterator_to_array($files))->toBeEmpty();
});
