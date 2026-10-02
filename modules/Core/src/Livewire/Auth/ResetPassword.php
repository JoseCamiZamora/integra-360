<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Models\User;

/**
 * New password from the e-mailed link (/recuperar/{token}?email=...).
 */
#[Layout('layouts::driver')]
final class ResetPassword extends Component
{
    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function save(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)],
        ], attributes: [
            'email' => __('core::auth.forgot.email'),
            'password' => __('core::auth.change_password.password'),
        ]);

        $status = Password::broker()->reset(
            [
                'email' => mb_strtolower(trim($this->email)),
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'must_change_password' => false,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __('core::auth.reset.invalid')]);
        }

        session()->flash('status', __('core::auth.reset.done'));

        $this->redirectRoute('login');
    }

    public function render(): View
    {
        return view('core::livewire.auth.reset-password')->title(__('core::auth.reset.title'));
    }
}
