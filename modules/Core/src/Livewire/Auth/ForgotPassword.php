<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Recovery by e-mail. The answer is the same whether the e-mail exists or
 * not, so accounts cannot be discovered. Users without e-mail ask their
 * company administrator for a temporary password.
 */
#[Layout('layouts::driver')]
final class ForgotPassword extends Component
{
    public string $email = '';

    public bool $sent = false;

    public function send(): void
    {
        $this->validate(['email' => ['required', 'email', 'max:255']], attributes: [
            'email' => __('core::auth.forgot.email'),
        ]);

        Password::broker()->sendResetLink(['email' => mb_strtolower(trim($this->email))]);

        $this->sent = true;
    }

    public function render(): View
    {
        return view('core::livewire.auth.forgot-password')->title(__('core::auth.forgot.title'));
    }
}
