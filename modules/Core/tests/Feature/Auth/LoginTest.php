<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Modules\Core\Actions\AuthenticateUser;
use Modules\Core\Enums\AuditEvent;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Livewire\Auth\ChangePassword;
use Modules\Core\Livewire\Auth\ChooseCompany;
use Modules\Core\Livewire\Auth\ChooseInterface;
use Modules\Core\Livewire\Auth\Login;
use Modules\Core\Models\AuditEntry;
use Modules\Core\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedCore();
    RateLimiter::clear('login:1020304050|127.0.0.1');

    $this->company = createCompany();
});

describe('driver without e-mail (criterion 3)', function (): void {
    beforeEach(function (): void {
        $this->driver = createMember($this->company, CompanyRole::Driver, [
            'document_number' => '1020304050',
            'email' => null,
            'password' => 'KXPM-4729',
            'must_change_password' => true,
        ]);
    });

    it('signs in with the document number, even typed with dots', function (string $typed): void {
        Livewire::test(Login::class)
            ->set('identifier', $typed)
            ->set('password', 'KXPM-4729')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect(route('password.change'));

        $this->assertAuthenticatedAs($this->driver);
    })->with(['1020304050', '1.020.304.050', ' 1020304050 ']);

    it('must change the temporary password before using the driver view', function (): void {
        $this->actingAs($this->driver)->get('/conductor')->assertRedirect(route('password.change'));

        Livewire::actingAs($this->driver)
            ->test(ChangePassword::class)
            ->set('password', 'mi-clave-nueva-1')
            ->set('password_confirmation', 'mi-clave-nueva-1')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('driver.home'));

        $driver = $this->driver->fresh();

        expect($driver->must_change_password)->toBeFalse()
            ->and(Hash::check('mi-clave-nueva-1', $driver->password))->toBeTrue();

        $this->actingAs($driver)->get('/conductor')->assertOk();
    });

    it('rejects keeping the temporary password or a short one', function (string $password, string $error): void {
        $component = Livewire::actingAs($this->driver)
            ->test(ChangePassword::class)
            ->set('password', $password)
            ->set('password_confirmation', $password)
            ->call('save')
            ->assertHasErrors('password');

        expect($component->errors()->first('password'))->toContain($error);
    })->with([
        'temporary' => ['KXPM-4729', 'distinta de la temporal'],
        'short' => ['corta', '8 caracteres'],
    ]);

    it('records the password change in the audit log, never the password', function (): void {
        Livewire::actingAs($this->driver)
            ->test(ChangePassword::class)
            ->set('password', 'mi-clave-nueva-1')
            ->set('password_confirmation', 'mi-clave-nueva-1')
            ->call('save');

        $entry = CompanyContext::run($this->company, fn () => AuditEntry::query()->where('event', AuditEvent::PasswordChanged->value)->sole());

        expect($entry->subject_id)->toBe((string) $this->driver->getKey())
            ->and(json_encode($entry->toArray()))->not->toContain('mi-clave-nueva-1');
    });

    it('keeps the driver signed in for 30 days with "remember me"', function (): void {
        $this->driver->forceFill(['must_change_password' => false])->save();

        Livewire::test(Login::class)
            ->set('identifier', '1020304050')
            ->set('password', 'KXPM-4729')
            ->set('remember', true)
            ->call('authenticate')
            ->assertRedirect(route('driver.home'));

        $cookie = collect(Cookie::getQueuedCookies())
            ->first(fn ($cookie): bool => $cookie->getName() === Auth::guard('web')->getRecallerName());

        expect($cookie)->not->toBeNull()
            ->and($cookie->getExpiresTime())->toBe(now()->addMinutes(AuthenticateUser::DRIVER_REMEMBER_MINUTES)->getTimestamp());
    });
});

describe('panel users', function (): void {
    it('signs in with the e-mail and goes to the company panel', function (): void {
        $admin = createMember($this->company, CompanyRole::CompanyAdmin, ['email' => 'laura@ejemplo.test']);

        Livewire::test(Login::class)
            ->set('identifier', 'Laura@Ejemplo.test')
            ->set('password', 'password')
            ->call('authenticate')
            ->assertRedirect('/app/'.$this->company->getKey());

        $this->assertAuthenticatedAs($admin);
        expect($admin->fresh()->last_login_at)->not->toBeNull();
    });

    it('does not give panel users the long session', function (): void {
        createMember($this->company, CompanyRole::CompanyAdmin, ['document_number' => '55555555']);

        Livewire::test(Login::class)
            ->set('identifier', '55555555')
            ->set('password', 'password')
            ->set('remember', true)
            ->call('authenticate');

        $this->assertAuthenticated();

        expect(collect(Cookie::getQueuedCookies())->map->getName())
            ->not->toContain(Auth::guard('web')->getRecallerName());
    });

    it('rejects wrong credentials with a generic message', function (string $identifier, string $password): void {
        createMember($this->company, CompanyRole::CompanyAdmin, ['document_number' => '55555555']);

        Livewire::test(Login::class)
            ->set('identifier', $identifier)
            ->set('password', $password)
            ->call('authenticate')
            ->assertHasErrors(['identifier' => __('core::auth.failed')]);

        $this->assertGuest();
    })->with([
        'wrong password' => ['55555555', 'otra'],
        'unknown document' => ['99999999', 'password'],
        'unknown e-mail' => ['nadie@ejemplo.test', 'password'],
    ]);

    it('does not let users of an inactive company in', function (): void {
        createMember($this->company, CompanyRole::CompanyAdmin, ['document_number' => '55555555']);
        $this->company->update(['is_active' => false]);

        Livewire::test(Login::class)
            ->set('identifier', '55555555')
            ->set('password', 'password')
            ->call('authenticate')
            ->assertHasErrors(['identifier' => __('core::auth.no_active_company')]);

        $this->assertGuest();
    });

    it('does not let a deactivated member in', function (): void {
        createMember($this->company, CompanyRole::CompanyAdmin, ['document_number' => '55555555'], active: false);

        Livewire::test(Login::class)
            ->set('identifier', '55555555')
            ->set('password', 'password')
            ->call('authenticate')
            ->assertHasErrors(['identifier']);
    });

    it('lets a driver with another role choose the interface', function (): void {
        $user = createMember($this->company, [CompanyRole::Driver, CompanyRole::AreaManager], ['document_number' => '55555555']);

        Livewire::test(Login::class)
            ->set('identifier', '55555555')
            ->set('password', 'password')
            ->call('authenticate')
            ->assertRedirect(route('interface.choose'));

        Livewire::actingAs($user)->test(ChooseInterface::class)
            ->call('choose', 'driver')
            ->assertRedirect(route('driver.home'));

        Livewire::actingAs($user)->test(ChooseInterface::class)
            ->call('choose', 'panel')
            ->assertRedirect('/app/'.$this->company->getKey());
    });

    it('sends the platform administrator to /plataforma', function (): void {
        User::factory()->platformAdmin()->create(['document_number' => '1000000001']);

        Livewire::test(Login::class)
            ->set('identifier', '1000000001')
            ->set('password', 'password')
            ->call('authenticate')
            ->assertRedirect('/plataforma');
    });

    it('records successful and failed sign-ins for the user\'s company', function (): void {
        createMember($this->company, CompanyRole::CompanyAdmin, ['document_number' => '55555555']);

        Livewire::test(Login::class)->set('identifier', '55555555')->set('password', 'mala')->call('authenticate');
        Livewire::test(Login::class)->set('identifier', '55555555')->set('password', 'password')->call('authenticate');

        $events = CompanyContext::run($this->company, fn () => AuditEntry::query()->pluck('event')->all());

        expect($events)->toContain(AuditEvent::LoginFailed->value, AuditEvent::Login->value);

        $failed = CompanyContext::run($this->company, fn () => AuditEntry::query()->where('event', AuditEvent::LoginFailed->value)->sole());

        expect($failed->getProperty('identifier'))->toBe('55555555')
            ->and(json_encode($failed->toArray()))->not->toContain('mala')
            ->and($failed->ip_address)->toBe('127.0.0.1');
    });
});

describe('several companies (criterion 4)', function (): void {
    it('chooses the company at sign-in and sees only its data', function (): void {
        $other = createCompany(['trade_name' => 'Segunda Empresa']);
        $user = createMember($this->company, CompanyRole::CompanyAdmin, ['document_number' => '55555555']);
        addMembership($user, $other, CompanyRole::CompanyAdmin);

        CompanyContext::run($this->company, fn () => $this->company->branches()->first()?->update(['name' => 'Sede de la primera']));
        CompanyContext::run($other, fn () => $other->branches()->first()?->update(['name' => 'Sede de la segunda']));

        Livewire::test(Login::class)
            ->set('identifier', '55555555')
            ->set('password', 'password')
            ->call('authenticate')
            ->assertRedirect(route('company.choose'));

        Livewire::actingAs($user)->test(ChooseCompany::class)
            ->assertSee('Segunda Empresa')
            ->call('choose', $other->getKey())
            ->assertRedirect('/app/'.$other->getKey());

        $this->actingAs($user)->get('/app/'.$other->getKey().'/branches')
            ->assertOk()
            ->assertSee('Sede de la segunda')
            ->assertDontSee('Sede de la primera');

        // Switching from the sidebar: the URL carries the company.
        $this->actingAs($user)->get('/app/'.$this->company->getKey().'/branches')
            ->assertOk()
            ->assertSee('Sede de la primera')
            ->assertDontSee('Sede de la segunda')
            ->assertSessionHas('company_id', $this->company->getKey());
    });

    it('cannot choose a company the user does not belong to', function (): void {
        $foreign = createCompany();
        $user = createMember($this->company, CompanyRole::CompanyAdmin);

        Livewire::actingAs($user)->test(ChooseCompany::class)
            ->call('choose', $foreign->getKey())
            ->assertForbidden();
    });

    it('shows the company switcher in the sidebar, with no topbar', function (): void {
        $other = createCompany(['trade_name' => 'Otra Flota']);
        $user = createMember($this->company, CompanyRole::CompanyAdmin);
        addMembership($user, $other, CompanyRole::Viewer);

        $this->actingAs($user)->get('/app/'.$this->company->getKey())
            ->assertOk()
            ->assertSee('fi-tenant-menu', escape: false)
            ->assertSee('Otra Flota')
            ->assertSee('fi-sidebar-footer', escape: false)
            ->assertDontSee('fi-topbar-ctn', escape: false);
    });
});

describe('throttling (criterion 9)', function (): void {
    it('blocks the sixth attempt within a minute, even with the right password', function (): void {
        createMember($this->company, CompanyRole::Driver, ['document_number' => '1020304050']);

        foreach (range(1, AuthenticateUser::MAX_ATTEMPTS) as $attempt) {
            Livewire::test(Login::class)
                ->set('identifier', '1020304050')
                ->set('password', 'mala-'.$attempt)
                ->call('authenticate')
                ->assertHasErrors(['identifier' => __('core::auth.failed')]);
        }

        $blocked = Livewire::test(Login::class)
            ->set('identifier', '1020304050')
            ->set('password', 'password')
            ->call('authenticate')
            ->assertHasErrors('identifier');

        expect($blocked->errors()->first('identifier'))->toContain('Demasiados intentos');

        $this->assertGuest();

        $this->travel(61)->seconds();

        Livewire::test(Login::class)
            ->set('identifier', '1020304050')
            ->set('password', 'password')
            ->call('authenticate')
            ->assertHasNoErrors();
    });
});

it('signs out back to /ingreso', function (): void {
    $user = createMember($this->company, CompanyRole::CompanyAdmin);

    $this->actingAs($user)->post('/salir')->assertRedirect('/ingreso');

    $this->assertGuest();
});

it('sends signed-in users away from /ingreso to their interface', function (): void {
    $driver = createMember($this->company, CompanyRole::Driver);

    $this->actingAs($driver)->get('/ingreso')->assertRedirect(route('driver.home'));
});
