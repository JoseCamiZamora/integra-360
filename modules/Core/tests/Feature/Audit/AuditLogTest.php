<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use Filament\Actions\EditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Enums\AuditEvent;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Filament\Resources\Users\Pages\ManageUsers;
use Modules\Core\Models\AuditEntry;
use Modules\Core\Models\Membership;
use Modules\Core\Models\User;

/*
 * Criterion 8: creating, editing and deactivating a user is recorded with
 * the values before and after. Passwords never reach the log.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedCore();

    $this->company = createCompany();
    $this->admin = createMember($this->company, CompanyRole::CompanyAdmin, ['name' => 'Laura Admin']);

    actingInPanel($this->admin, $this->company);
});

function auditFor(string $event, string $subjectClass): AuditEntry
{
    return AuditEntry::query()
        ->where('event', $event)
        ->where('subject_type', $subjectClass)
        ->latest('id')
        ->firstOrFail();
}

it('records the creation, edition and deactivation of a user', function (): void {
    Livewire::test(ManageUsers::class)
        ->callAction('create', [
            'name' => 'Luis Conductor',
            'document_type' => 'CC',
            'document_number' => '1020304050',
            'email' => '',
            'phone' => '3001234567',
            'roles' => ['driver'],
        ])
        ->assertHasNoActionErrors();

    $user = User::query()->where('document_number', '1020304050')->sole();

    $created = auditFor(AuditEvent::Created->value, User::class);

    expect($created->company_id)->toBe($this->company->getKey())
        ->and($created->causer_id)->toBe((string) $this->admin->getKey())
        ->and($created->ip_address)->toBe('127.0.0.1')
        ->and($created->attribute_changes['attributes'])->toMatchArray([
            'name' => 'Luis Conductor',
            'document_number' => '1020304050',
            'email' => null,
            'must_change_password' => true,
        ])
        ->and($created->attribute_changes['attributes'])->not->toHaveKey('password')
        ->and(AuditEntry::query()->where('event', AuditEvent::RoleAssigned->value)->latest('id')->first()?->getProperty('roles'))->toBe(['driver']);

    Livewire::test(ManageUsers::class)
        ->callTableAction(EditAction::class, $user->getKey(), [
            'name' => 'Luis Alberto Conductor',
            'document_type' => 'CC',
            'document_number' => '1020304050',
            'email' => 'luis@ejemplo.test',
            'phone' => '3001234567',
            'roles' => ['driver', 'viewer'],
        ])
        ->assertHasNoTableActionErrors();

    $updated = auditFor(AuditEvent::Updated->value, User::class);

    expect($updated->attribute_changes['old'])->toBe(['name' => 'Luis Conductor', 'email' => null])
        ->and($updated->attribute_changes['attributes'])->toBe(['name' => 'Luis Alberto Conductor', 'email' => 'luis@ejemplo.test'])
        ->and(AuditEntry::query()->where('event', AuditEvent::RoleAssigned->value)->latest('id')->first()?->getProperty('roles'))->toBe(['viewer'])
        ->and(AuditEntry::query()->where('event', AuditEvent::RoleRemoved->value)->count())->toBe(0);

    Livewire::test(ManageUsers::class)->callTableAction('deactivate', $user->getKey());

    $deactivated = auditFor(AuditEvent::Updated->value, Membership::class);

    expect($deactivated->attribute_changes['old'])->toBe(['is_active' => true])
        ->and($deactivated->attribute_changes['attributes'])->toBe(['is_active' => false])
        ->and($deactivated->getProperty('subject_label'))->toBe('Luis Alberto Conductor')
        ->and($deactivated->company_id)->toBe($this->company->getKey());
});

it('records a password reset by an administrator without the password', function (): void {
    $user = createMember($this->company, CompanyRole::Driver, ['email' => null]);

    Livewire::test(ManageUsers::class)->callTableAction('resetPassword', $user->getKey());

    $entry = auditFor(AuditEvent::PasswordResetByAdmin->value, User::class);
    $user->refresh();

    expect($entry->causer_id)->toBe((string) $this->admin->getKey())
        ->and($user->must_change_password)->toBeTrue();

    expect(AuditEntry::query()->withoutGlobalScopes()->get()->toJson())
        ->not->toContain($user->password)
        ->not->toContain('"password"');
});

it('records changes to branches, memberships and licenses', function (): void {
    $branch = $this->company->branches()->first();
    $branch->update(['phone' => '3009998877']);

    expect(auditFor(AuditEvent::Updated->value, $branch::class)->attribute_changes['attributes'])->toBe(['phone' => '3009998877']);

    $license = createLicense($this->company);

    expect(auditFor(AuditEvent::Created->value, $license::class)->getProperty('subject_label'))->toBe('pesv');
});

it('records company changes in that company\'s log', function (): void {
    CompanyContext::forget();

    $this->company->update(['phone' => '6017654321']);

    $entry = CompanyContext::run($this->company, fn () => auditFor(AuditEvent::Updated->value, $this->company::class));

    expect($entry->attribute_changes['old'])->toHaveKey('phone')
        ->and($entry->company_id)->toBe($this->company->getKey());
});

it('offers no way to change the audit log from the panel', function (): void {
    $entry = AuditEntry::query()->firstOrFail();

    expect($this->admin->can('create', AuditEntry::class))->toBeFalse()
        ->and($this->admin->can('update', $entry))->toBeFalse()
        ->and($this->admin->can('delete', $entry))->toBeFalse()
        ->and($this->admin->can('viewAny', AuditEntry::class))->toBeTrue();

    $viewer = createMember($this->company, CompanyRole::Viewer);

    expect($viewer->forgetCompanyRoles()->can('viewAny', AuditEntry::class))->toBeFalse();
});
