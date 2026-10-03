<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Core\Actions\CreatePersonAccess;
use Modules\Core\Actions\SetPersonStatus;
use Modules\Core\Actions\SyncDriverProfile;
use Modules\Core\Actions\SyncUserRoles;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Enums\PersonStatus;
use Modules\Core\Models\AuditEntry;
use Modules\Core\Models\Driver;
use Modules\Core\Models\Membership;
use Modules\Core\Models\Person;
use Modules\Core\Models\User;

/*
 * "Crear acceso al sistema" from a person, and the membership following the
 * person's status (reversible, audited).
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedCore();

    $this->company = createCompany();
    $this->admin = createMember($this->company, CompanyRole::CompanyAdmin);

    $this->actingAs($this->admin);
    CompanyContext::activate($this->company);

    $this->driverPerson = Person::factory()->create(['document_number' => '99000333', 'email' => null]);
    Driver::factory()->for($this->driverPerson)->create();
    $this->driverPerson->refresh();
});

afterEach(fn () => CompanyContext::forget());

describe('creating access', function (): void {
    it('creates the account of a driver with a temporary password and the driver role', function (): void {
        $credentials = app(CreatePersonAccess::class)->handle($this->driverPerson);
        $user = $credentials->user;

        expect($this->driverPerson->fresh()->user_id)->toBe($user->getKey())
            ->and($user->document_number)->toBe('99000333')
            ->and($user->email)->toBeNull()
            ->and($user->must_change_password)->toBeTrue()
            ->and(Hash::check($credentials->password, $user->password))->toBeTrue()
            ->and($user->isActiveMemberOf($this->company))->toBeTrue()
            ->and($user->roleNamesInCompany($this->company))->toBe([CompanyRole::Driver->value]);

        expect(AuditEntry::query()->where('subject_type', User::class)->where('subject_id', $user->getKey())->pluck('event')->all())
            ->toContain('created', 'role_assigned');
    });

    it('asks for roles when the person does not drive', function (): void {
        $clerk = Person::factory()->create(['position' => 'Auxiliar']);

        expect(fn () => app(CreatePersonAccess::class)->handle($clerk))->toThrow(ValidationException::class);

        $user = app(CreatePersonAccess::class)->handle($clerk, [CompanyRole::Viewer->value])->user;

        expect($user->roleNamesInCompany($this->company))->toBe([CompanyRole::Viewer->value]);
    });

    it('rejects a document already registered to an account, without linking it', function (): void {
        $otherCompany = createCompany();
        createMember($otherCompany, CompanyRole::Viewer, ['document_type' => 'CC', 'document_number' => '99000333']);

        expect(fn () => app(CreatePersonAccess::class)->handle($this->driverPerson))
            ->toThrow(ValidationException::class, 'Ya existe una cuenta con este documento');

        expect($this->driverPerson->fresh()->user_id)->toBeNull();
    });

    it('rejects an e-mail already used by another account', function (): void {
        User::factory()->create(['email' => 'usada@ejemplo.test']);
        $person = Person::factory()->create(['email' => 'usada@ejemplo.test']);

        app(CreatePersonAccess::class)->handle($person, [CompanyRole::Viewer->value]);
    })->throws(ValidationException::class);

    it('creates the access only once and only for active people', function (): void {
        app(CreatePersonAccess::class)->handle($this->driverPerson);

        expect(fn () => app(CreatePersonAccess::class)->handle($this->driverPerson->fresh()))
            ->toThrow(ValidationException::class, 'ya tiene acceso');

        $retired = Person::factory()->inactive()->create();

        expect(fn () => app(CreatePersonAccess::class)->handle($retired, [CompanyRole::Viewer->value]))
            ->toThrow(ValidationException::class, 'Reactive');
    });
});

describe('status and membership', function (): void {
    beforeEach(function (): void {
        $this->user = app(CreatePersonAccess::class)->handle($this->driverPerson)->user;
        $this->membership = Membership::query()->where('user_id', $this->user->getKey())->sole();
    });

    it('deactivates the membership when the person leaves, and reactivates it when they return', function (): void {
        app(SetPersonStatus::class)->handle($this->driverPerson, PersonStatus::Inactive);

        expect($this->driverPerson->fresh()->status)->toBe(PersonStatus::Inactive)
            ->and($this->membership->fresh()->is_active)->toBeFalse()
            ->and($this->user->fresh()->isActiveMemberOf($this->company))->toBeFalse();

        app(SetPersonStatus::class)->handle($this->driverPerson, PersonStatus::Active);

        expect($this->driverPerson->fresh()->status)->toBe(PersonStatus::Active)
            ->and($this->membership->fresh()->is_active)->toBeTrue()
            ->and($this->user->fresh()->isActiveMemberOf($this->company))->toBeTrue();
    });

    it('records both directions in the audit log', function (): void {
        app(SetPersonStatus::class)->handle($this->driverPerson, PersonStatus::Inactive);
        app(SetPersonStatus::class)->handle($this->driverPerson, PersonStatus::Active);

        $personChanges = AuditEntry::query()->where('subject_type', 'person')->where('event', 'updated')->orderBy('id')->get()
            ->map(fn (AuditEntry $entry): mixed => $entry->attribute_changes['attributes']['status'] ?? null)
            ->filter()->values()->all();

        $membershipChanges = AuditEntry::query()->where('subject_type', Membership::class)->where('subject_id', $this->membership->getKey())->where('event', 'updated')->orderBy('id')->get()
            ->map(fn (AuditEntry $entry): mixed => $entry->attribute_changes['attributes']['is_active'])
            ->all();

        expect($personChanges)->toBe(['inactive', 'active'])
            ->and($membershipChanges)->toBe([false, true]);
    });

    it('does not touch the membership in other companies', function (): void {
        $other = createCompany();
        addMembership($this->user, $other, CompanyRole::Viewer);

        app(SetPersonStatus::class)->handle($this->driverPerson, PersonStatus::Inactive);

        expect($this->user->fresh()->isActiveMemberOf($other))->toBeTrue();
    });

    it('needs user deactivation rights to retire a person with an account', function (): void {
        $leader = createMember($this->company, CompanyRole::PesvLeader);
        $withoutAccount = Person::factory()->create();

        expect($leader->can('changeStatus', $withoutAccount))->toBeTrue()
            ->and($leader->can('changeStatus', $this->driverPerson->fresh()))->toBeFalse()
            ->and($this->admin->can('changeStatus', $this->driverPerson->fresh()))->toBeTrue();
    });

    it('keeps the driver role in line with the driver profile', function (): void {
        app(SyncUserRoles::class)->handle($this->user, [CompanyRole::Driver->value, CompanyRole::Viewer->value]);

        app(SyncDriverProfile::class)->handle($this->driverPerson->fresh(), null);

        expect($this->user->roleNamesInCompany($this->company))->toBe([CompanyRole::Viewer->value]);

        app(SyncDriverProfile::class)->handle($this->driverPerson->fresh(), ['license_number' => '99000333', 'license_category' => 'C2']);

        expect($this->user->roleNamesInCompany($this->company))->toEqualCanonicalizing([CompanyRole::Viewer->value, CompanyRole::Driver->value]);
    });

    it('never leaves an account without roles when the profile is removed', function (): void {
        app(SyncDriverProfile::class)->handle($this->driverPerson->fresh(), null);

        expect($this->user->roleNamesInCompany($this->company))->toBe([CompanyRole::Driver->value]);
    });
});
