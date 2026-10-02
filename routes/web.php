<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Module routes live in modules/<Module>/routes/web.php. Guests are sent to
// /ingreso; signed-in users to their interface (see Core's ResolveHomeUrl).
Route::redirect('/', '/ingreso');
