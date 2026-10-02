<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::driver')]
final class NoAccess extends Component
{
    public function render(): View
    {
        return view('core::livewire.auth.no-access')->title(__('core::auth.no_access.title'));
    }
}
