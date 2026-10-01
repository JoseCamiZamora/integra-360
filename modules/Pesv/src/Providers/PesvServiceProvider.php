<?php

declare(strict_types=1);

namespace Modules\Pesv\Providers;

use App\Modules\ModuleServiceProvider;

final class PesvServiceProvider extends ModuleServiceProvider
{
    public function code(): string
    {
        return 'pesv';
    }
}
