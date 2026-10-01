<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2026, 10, 1, 8, 30, 0, 'America/Bogota'));
});

it('redirects the home page to the admin panel', function (): void {
    $this->get('/')->assertRedirect('/app');
});

it('shows the admin panel home in Spanish', function (): void {
    $this->get('/app')
        ->assertOk()
        ->assertSee('Integra 360')
        ->assertSee('Estado de la operación')
        ->assertSee('Jueves, 1 de octubre de 2026')
        ->assertSee('Inicio');
});

it('shows the mobile driver view', function (): void {
    $this->get('/conductor')
        ->assertOk()
        ->assertSee('<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">', escape: false)
        ->assertSee('¡Hola!')
        ->assertSee('Jueves, 1 de octubre')
        ->assertSee('Aquí verás tu vehículo asignado');
});

it('ships no JavaScript in the driver view besides Livewire', function (): void {
    $html = $this->get('/conductor')->getContent();

    preg_match_all('/<script[^>]*src="([^"]+)"/', (string) $html, $scripts);

    foreach ($scripts[1] as $src) {
        expect($src)->toContain('livewire');
    }
});

it('blocks the provisional routes in production unless explicitly enabled', function (string $uri): void {
    app()->instance('env', 'production');

    $this->get($uri)->assertForbidden();

    config(['integra.provisional_access' => true]);

    $this->get($uri)->assertOk();
})->with(['/app', '/conductor']);
