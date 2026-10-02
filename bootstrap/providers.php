<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Modules\Core\Providers\Filament\AdminPanelProvider;
use Modules\Core\Providers\Filament\PlatformPanelProvider;

// AppServiceProvider registers the module providers first, so their Filament
// components are configured before the panels are built.
return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    PlatformPanelProvider::class,
];
