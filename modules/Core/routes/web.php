<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\LogoutController;
use Modules\Core\Livewire\Auth\ChangePassword;
use Modules\Core\Livewire\Auth\ChooseCompany;
use Modules\Core\Livewire\Auth\ChooseInterface;
use Modules\Core\Livewire\Auth\ForgotPassword;
use Modules\Core\Livewire\Auth\Login;
use Modules\Core\Livewire\Auth\NoAccess;
use Modules\Core\Livewire\Auth\ResetPassword;
use Modules\Core\Livewire\DriverHome;

/*
|--------------------------------------------------------------------------
| Core web routes
|--------------------------------------------------------------------------
|
| Loaded by CoreServiceProvider inside the "web" middleware group.
| /ingreso is the single sign-in screen for every interface; the Filament
| panels (/app, /plataforma) send guests here.
|
*/

Route::middleware('guest')->group(function (): void {
    Route::get('/ingreso', Login::class)->name('login');
    Route::get('/recuperar', ForgotPassword::class)->name('password.request');
    Route::get('/recuperar/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/salir', LogoutController::class)->name('logout');
    Route::get('/ingreso/contrasena', ChangePassword::class)->name('password.change');

    Route::middleware('password.changed')->group(function (): void {
        Route::get('/ingreso/empresa', ChooseCompany::class)->name('company.choose');
        Route::get('/ingreso/interfaz', ChooseInterface::class)->middleware('company.active')->name('interface.choose');
        Route::get('/sin-acceso', NoAccess::class)->name('no-access');

        Route::get('/conductor', DriverHome::class)
            ->middleware(['company.active', 'can:core.driver.access'])
            ->name('driver.home');
    });
});
