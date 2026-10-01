<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureProvisionalAccess;
use Illuminate\Support\Facades\Route;
use Modules\Core\Livewire\DriverHome;

/*
|--------------------------------------------------------------------------
| Core web routes
|--------------------------------------------------------------------------
|
| Loaded by CoreServiceProvider inside the "web" middleware group.
|
*/

// Provisional gate until I360-01 adds authentication.
Route::middleware(EnsureProvisionalAccess::class)->group(function (): void {
    Route::get('/conductor', DriverHome::class)->name('driver.home');
});
