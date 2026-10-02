<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Actions\ChangeOwnPassword;
use Modules\Core\Actions\ResolveHomeUrl;
use Modules\Core\Models\User;

/**
 * Mandatory password change after a temporary password.
 */
#[Layout('layouts::driver')]
final class ChangePassword extends Component
{
    public string $password = '';

    public string $password_confirmation = '';

    public function mount(ResolveHomeUrl $home): void
    {
        if (! $this->user()->must_change_password) {
            $this->redirect($home->handle($this->user(), session()->driver()));
        }
    }

    public function save(ChangeOwnPassword $change, ResolveHomeUrl $home): void
    {
        $this->validate([
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ], attributes: [
            'password' => __('core::auth.change_password.password'),
        ]);

        if (Hash::check($this->password, $this->user()->password)) {
            throw ValidationException::withMessages(['password' => __('core::auth.change_password.same_as_temporary')]);
        }

        $change->handle($this->user(), $this->password);

        $this->redirect($home->handle($this->user(), session()->driver()));
    }

    public function render(): View
    {
        return view('core::livewire.auth.change-password')->title(__('core::auth.change_password.title'));
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
