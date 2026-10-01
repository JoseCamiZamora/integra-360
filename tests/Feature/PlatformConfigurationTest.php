<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

it('uses the Colombian regional configuration', function (): void {
    expect(config('app.locale'))->toBe('es')
        ->and(config('app.fallback_locale'))->toBe('es')
        ->and(config('app.timezone'))->toBe('America/Bogota')
        ->and(config('app.date_format'))->toBe('d/m/Y')
        ->and(config('app.currency'))->toBe('COP')
        ->and(__('validation.required', ['attribute' => 'placa']))->toBe('El campo placa es obligatorio.');
});

it('runs tests against MySQL, never SQLite', function (): void {
    expect(config('database.default'))->toBe('mysql')
        ->and(config('database.connections.mysql.database'))->toBe('integra360_testing');
});

it('schedules a heartbeat that logs that the scheduler is alive', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => $event->description === 'scheduler-heartbeat');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/15 * * * *');

    Log::shouldReceive('info')->once()->withArgs(fn (string $message): bool => str_contains($message, 'heartbeat'));

    $event->run(app());
});

it('refuses to boot in production with a non S3 default disk', function (): void {
    app()->instance('env', 'production');
    config(['filesystems.default' => 'local']);

    (new AppServiceProvider(app()))->boot();
})->throws(RuntimeException::class, 'is not S3-compatible');

it('boots in production with an S3-compatible default disk', function (): void {
    app()->instance('env', 'production');
    config(['filesystems.default' => 's3']);

    (new AppServiceProvider(app()))->boot();

    expect(true)->toBeTrue();
});
