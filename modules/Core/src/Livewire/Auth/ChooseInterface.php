<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Actions\ResolveHomeUrl;
use Modules\Core\Models\User;

/**
 * A driver who also has another role chooses, once per session, between the
 * driver view and the panel.
 */
#[Layout('layouts::driver')]
final class ChooseInterface extends Component
{
    public function choose(string $interface, ResolveHomeUrl $home): void
    {
        abort_unless(in_array($interface, [ResolveHomeUrl::INTERFACE_DRIVER, ResolveHomeUrl::INTERFACE_PANEL], true), 400);

        session()->put('interface', $interface);

        /** @var User $user */
        $user = auth()->user();

        $this->redirect($home->handle($user, session()->driver()));
    }

    public function render(): View
    {
        return view('core::livewire.auth.choose-interface')->title(__('core::auth.choose_interface.title'));
    }
}
