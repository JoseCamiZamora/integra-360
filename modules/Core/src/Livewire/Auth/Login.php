<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Actions\AuthenticateUser;
use Modules\Core\Actions\ResolveHomeUrl;

/**
 * /ingreso: the single sign-in screen (drivers and administrators), with
 * document number or e-mail in the same field.
 */
#[Layout('layouts::driver')]
final class Login extends Component
{
    public string $identifier = '';

    public string $password = '';

    public bool $remember = false;

    public function authenticate(AuthenticateUser $authenticate, ResolveHomeUrl $home): void
    {
        $this->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], attributes: [
            'identifier' => __('core::auth.login.identifier'),
            'password' => __('core::auth.login.password'),
        ]);

        $user = $authenticate->handle($this->identifier, $this->password, $this->remember, request());

        session()->regenerate();
        session()->forget(['company_id', 'interface']);

        $this->redirect($home->handle($user, session()->driver()));
    }

    public function render(): View
    {
        return view('core::livewire.auth.login')->title(__('core::auth.login.title'));
    }
}
