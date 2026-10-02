<?php

declare(strict_types=1);

use Filament\Actions\EditAction;
use Filament\Notifications\Livewire\Notifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Filament\Resources\Users\Pages\ManageUsers;
use Modules\Core\Models\Membership;
use Modules\Core\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedCore();

    $this->company = createCompany();
    $this->admin = createMember($this->company, CompanyRole::CompanyAdmin, ['name' => 'Laura Admin']);

    actingInPanel($this->admin, $this->company);
});

it('creates a driver without e-mail and shows the temporary password once', function (): void {
    Livewire::test(ManageUsers::class)
        ->callAction('create', [
            'name' => 'Luis Conductor',
            'document_type' => 'CC',
            'document_number' => '1.020.304.050',
            'roles' => ['driver'],
        ])
        ->assertHasNoActionErrors();

    $user = User::query()->where('document_number', '1020304050')->sole();

    // The persistent notification is the only place the password appears.
    $notifications = new Notifications;
    $notifications->mount();
    $notification = $notifications->notifications->last();

    preg_match('/\*\*([A-Z]{4}-\d{4})\*\*/', (string) $notification?->getBody(), $match);

    expect($user->email)->toBeNull()
        ->and($user->must_change_password)->toBeTrue()
        ->and($match)->toHaveCount(2)
        ->and(Hash::check($match[1], $user->password))->toBeTrue()
        ->and($user->roleNamesInCompany($this->company))->toBe(['driver'])
        ->and(Membership::query()->where('user_id', $user->getKey())->value('is_active'))->toBeTrue();
});

it('searches users by name or document', function (): void {
    $luis = createMember($this->company, CompanyRole::Driver, ['name' => 'Luis Conductor', 'document_number' => '1020304050']);
    $ana = createMember($this->company, CompanyRole::Viewer, ['name' => 'Ana Consulta', 'document_number' => '52000111']);

    Livewire::test(ManageUsers::class)
        ->searchTable('Luis')
        ->assertCanSeeTableRecords([$luis])
        ->assertCanNotSeeTableRecords([$ana])
        ->searchTable('52000111')
        ->assertCanSeeTableRecords([$ana])
        ->assertCanNotSeeTableRecords([$luis]);
});

it('rejects a document that already has an account', function (): void {
    createMember(createCompany(), CompanyRole::Driver, ['document_number' => '1020304050']);

    Livewire::test(ManageUsers::class)
        ->callAction('create', [
            'name' => 'Otra Persona',
            'document_type' => 'CC',
            'document_number' => '1020304050',
            'roles' => ['driver'],
        ])
        ->assertHasActionErrors(['document_number' => 'unique']);
});

it('requires at least one role', function (): void {
    Livewire::test(ManageUsers::class)
        ->callAction('create', [
            'name' => 'Sin Rol',
            'document_type' => 'CC',
            'document_number' => '1020304050',
            'roles' => [],
        ])
        ->assertHasActionErrors(['roles']);
});

it('edits data and roles of a user', function (): void {
    $user = createMember($this->company, CompanyRole::Driver);

    Livewire::test(ManageUsers::class)
        ->callTableAction(EditAction::class, $user->getKey(), [
            'name' => 'Nombre Nuevo',
            'document_type' => 'CE',
            'document_number' => 'AB12345',
            'roles' => ['area_manager'],
        ])
        ->assertHasNoTableActionErrors();

    $user->refresh();

    expect($user->name)->toBe('Nombre Nuevo')
        ->and($user->document_type->value)->toBe('CE')
        ->and($user->roleNamesInCompany($this->company))->toBe(['area_manager']);
});

it('deactivates and reactivates a user, but not oneself', function (): void {
    $user = createMember($this->company, CompanyRole::Viewer);

    Livewire::test(ManageUsers::class)->callTableAction('deactivate', $user->getKey());
    expect($user->activeCompanies())->toBeEmpty();

    Livewire::test(ManageUsers::class)->callTableAction('activate', $user->getKey());
    expect($user->activeCompanies())->toHaveCount(1);

    Livewire::test(ManageUsers::class)->assertTableActionHidden('deactivate', $this->admin->getKey());
});

it('gives a user without e-mail a new temporary password', function (): void {
    $user = createMember($this->company, CompanyRole::Driver, ['email' => null]);
    $oldHash = $user->password;

    Livewire::test(ManageUsers::class)
        ->callTableAction('resetPassword', $user->getKey())
        ->assertNotified();

    $user->refresh();

    expect($user->password)->not->toBe($oldHash)
        ->and($user->must_change_password)->toBeTrue();
});

it('lets read-only roles see users but not manage them', function (): void {
    $viewer = createMember($this->company, CompanyRole::Viewer);
    $target = createMember($this->company, CompanyRole::Driver);

    actingInPanel($viewer->forgetCompanyRoles(), $this->company);

    Livewire::test(ManageUsers::class)
        ->assertCanSeeTableRecords([$target])
        ->assertActionHidden('create')
        ->assertTableActionHidden(EditAction::class, $target->getKey())
        ->assertTableActionHidden('deactivate', $target->getKey())
        ->assertTableActionHidden('resetPassword', $target->getKey());
});
