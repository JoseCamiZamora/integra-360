<?php

declare(strict_types=1);

namespace Modules\Core\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Driver home (/conductor). The vehicle card and the inspection button are
 * added by the PESV prompts; the greeting gets the driver's name in I360-01.
 */
#[Layout('layouts::driver')]
final class DriverHome extends Component
{
    public function render(): View
    {
        return view('core::livewire.driver-home', [
            'today' => Str::ucfirst(now()->translatedFormat(__('core::driver.date_format'))),
        ])->title(__('core::driver.title'));
    }
}
