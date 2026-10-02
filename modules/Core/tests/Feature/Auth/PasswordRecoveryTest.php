<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Core\Enums\AuditEvent;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Livewire\Auth\ForgotPassword;
use Modules\Core\Livewire\Auth\ResetPassword;
use Modules\Core\Models\AuditEntry;
use Modules\Core\Notifications\ResetPasswordNotification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedCore();

    $this->company = createCompany();
    $this->user = createMember($this->company, CompanyRole::CompanyAdmin, ['email' => 'laura@ejemplo.test', 'must_change_password' => true]);
});

it('e-mails a queued reset link and answers the same for unknown e-mails', function (): void {
    Notification::fake();

    Livewire::test(ForgotPassword::class)->set('email', 'laura@ejemplo.test')->call('send')->assertSet('sent', true);
    Livewire::test(ForgotPassword::class)->set('email', 'nadie@ejemplo.test')->call('send')->assertSet('sent', true);

    Notification::assertSentTo($this->user, ResetPasswordNotification::class);
    Notification::assertCount(1);

    expect(new ResetPasswordNotification('token'))->toBeInstanceOf(ShouldQueue::class);
});

it('sets a new password from the link and records it', function (): void {
    $token = app('auth.password.broker')->createToken($this->user);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', 'laura@ejemplo.test')
        ->set('password', 'nueva-clave-segura')
        ->set('password_confirmation', 'nueva-clave-segura')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    $user = $this->user->fresh();

    expect(Hash::check('nueva-clave-segura', $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeFalse()
        ->and(CompanyContext::run($this->company, fn () => AuditEntry::query()->where('event', AuditEvent::PasswordResetByEmail->value)->count()))->toBe(1);
});

it('rejects an invalid link', function (): void {
    Livewire::test(ResetPassword::class, ['token' => 'falso'])
        ->set('email', 'laura@ejemplo.test')
        ->set('password', 'nueva-clave-segura')
        ->set('password_confirmation', 'nueva-clave-segura')
        ->call('save')
        ->assertHasErrors(['email']);
});

it('tells users without e-mail to ask their administrator', function (): void {
    $this->get('/recuperar')->assertOk()->assertSee('Pide al administrador de tu empresa una contraseña temporal');
});
