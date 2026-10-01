<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| Run locally with `php artisan schedule:work`; in Laravel Cloud the
| scheduler is enabled per environment (see docs/deploy.md).
|
*/

// Heartbeat: proves in the logs that the scheduler is alive.
Schedule::call(fn () => Log::info('Scheduler heartbeat: the task scheduler is running.'))
    ->name('scheduler-heartbeat')
    ->everyFifteenMinutes();
