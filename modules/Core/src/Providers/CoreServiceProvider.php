<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use App\Modules\ModuleServiceProvider;

final class CoreServiceProvider extends ModuleServiceProvider
{
    public function code(): string
    {
        return 'core';
    }
}
