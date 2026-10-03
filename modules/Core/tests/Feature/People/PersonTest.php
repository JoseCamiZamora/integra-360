<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Actions\CreatePerson;
use Modules\Core\Actions\RegisterExpiringDocument;
use Modules\Core\Actions\UpdatePerson;
use Modules\Core\Contracts\DocumentCompliance;
use Modules\Core\Database\Seeders\DocumentTypeCatalogSeeder;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Enums\ComplianceStatus;
use Modules\Core\Enums\LicenseCategory;
use Modules\Core\Models\AuditEntry;
use Modules\Core\Models\DocumentType;
use Modules\Core\Models\Driver;
use Modules\Core\Models\Person;
use Modules\Core\Models\User;

/*
 * People and driver profiles: validation, isolation (criterion 1),
 * permissions, audit (criterion 12) and compliance of people.
 */

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function personData(array $overrides = []): array
{
    return array_merge([
        'document_type' => 'CC',
        'document_number' => '99.000.111',
        'first_name' => 'Ana María',
        'last_name' => 'Prueba Ficticia',
        'birth_date' => '1990-04-12',
        'phone' => '3000000001',
        'email' => 'ana.prueba@ejemplo.test',
        'position' => 'Conductora',
        'area' => 'Operaciones',
        'hired_at' => '2024-02-01',
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function driverData(array $overrides = []): array
{
    return array_merge([
        'license_number' => '99000111',
        'license_category' => 'C3',
        'experience_years' => 8,
    ], $overrides);
}

beforeEach(function (): void {
    seedCore();

    $this->companyA = createCompany();
    $this->companyB = createCompany();
    $this->adminA = createMember($this->companyA, CompanyRole::CompanyAdmin);

    $this->actingAs($this->adminA);
    CompanyContext::activate($this->companyA);
});

afterEach(fn () => CompanyContext::forget());

describe('people', function (): void {
    it('registers a person with a normalised document, active and without access', function (): void {
        $person = app(CreatePerson::class)->handle(personData());

        expect($person->document_number)->toBe('99000111')
            ->and($person->full_name)->toBe('Ana María Prueba Ficticia')
            ->and($person->isActive())->toBeTrue()
            ->and($person->user_id)->toBeNull()
            ->and($person->isDriver())->toBeFalse()
            ->and($person->company_id)->toBe($this->companyA->getKey());
    });

    it('keeps the document unique per company and type', function (): void {
        app(CreatePerson::class)->handle(personData());

        expect(fn () => app(CreatePerson::class)->handle(personData(['document_number' => '99000111'])))
            ->toThrow(ValidationException::class, 'Ya hay una persona con este documento');

        // Another document type, or another company: allowed.
        expect(app(CreatePerson::class)->handle(personData(['document_type' => 'CE']))->document_type->value)->toBe('CE');

        $inB = CompanyContext::run($this->companyB, fn () => app(CreatePerson::class)->handle(personData()));

        expect($inB->company_id)->toBe($this->companyB->getKey());
    });

    it('frees the document of a deleted person, and the database enforces uniqueness', function (): void {
        app(CreatePerson::class)->handle(personData())->delete();

        expect(app(CreatePerson::class)->handle(personData())->exists)->toBeTrue();

        Person::factory()->create(['document_number' => '99000111']);
    })->throws(QueryException::class);

    it('never finds or changes a person of another company', function (): void {
        $personB = CompanyContext::run($this->companyB, fn () => Person::factory()->create());

        expect(Person::query()->find($personB->getKey()))->toBeNull()
            ->and(Person::query()->whereKey($personB->getKey())->update(['first_name' => 'X']))->toBe(0)
            ->and(Driver::query()->count())->toBe(0)
            ->and($this->adminA->can('view', $personB))->toBeFalse()
            ->and($this->adminA->can('update', $personB))->toBeFalse()
            ->and($this->adminA->can('createAccess', $personB))->toBeFalse();
    });

    it('applies the role permissions', function (CompanyRole $role, bool $view, bool $write, bool $access): void {
        $user = createMember($this->companyA, $role);
        $person = Person::factory()->create();

        expect($user->can('view', $person))->toBe($view)
            ->and($user->can('create', Person::class))->toBe($write)
            ->and($user->can('update', $person))->toBe($write)
            ->and($user->can('manageDriverProfile', $person))->toBe($write)
            ->and($user->can('createAccess', $person))->toBe($access)
            ->and($user->can('forceDelete', $person))->toBeFalse();
    })->with([
        'company administrator' => [CompanyRole::CompanyAdmin, true, true, true],
        'PESV leader' => [CompanyRole::PesvLeader, true, true, false],
        'management' => [CompanyRole::Management, true, false, false],
        'area manager' => [CompanyRole::AreaManager, true, false, false],
        'read only' => [CompanyRole::Viewer, true, false, false],
        'driver' => [CompanyRole::Driver, false, false, false],
    ]);

    it('lets a driver view only their own record', function (): void {
        $driverUser = createMember($this->companyA, CompanyRole::Driver);
        $own = Person::factory()->create(['user_id' => $driverUser->getKey()]);
        $other = Person::factory()->create();

        expect($driverUser->can('view', $own))->toBeTrue()
            ->and($driverUser->can('view', $other))->toBeFalse()
            ->and($driverUser->can('update', $own))->toBeFalse();
    });

    it('denies company people to the platform administrator', function (): void {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

        expect($platformAdmin->can('view', Person::factory()->create()))->toBeFalse()
            ->and($platformAdmin->can('create', Person::class))->toBeFalse();
    });

    it('logs people without contact data or birth date', function (): void {
        $person = app(CreatePerson::class)->handle(personData(), driverData());

        $logged = AuditEntry::query()->whereIn('subject_type', ['person', Driver::class])->get()
            ->map(fn (AuditEntry $entry): string => json_encode([$entry->properties, $entry->attribute_changes]))
            ->implode(' ');

        expect($logged)->toContain('Prueba Ficticia')
            ->toContain('C3')
            ->not->toContain('1990-04-12')
            ->not->toContain('3000000001')
            ->not->toContain('ana.prueba@ejemplo.test')
            ->not->toContain('99000111');

        expect(AuditEntry::query()->where('subject_type', 'person')->where('subject_id', $person->getKey())->value('event'))->toBe('created');
    });
});

describe('driver profile', function (): void {
    it('creates, removes and restores the same profile', function (): void {
        $person = app(CreatePerson::class)->handle(personData(), driverData());
        $driver = $person->driver;

        expect($driver)->toBeInstanceOf(Driver::class)
            ->and($driver->license_category)->toBe(LicenseCategory::C3);

        app(UpdatePerson::class)->handle($person, personData(), null);

        expect($person->fresh()->isDriver())->toBeFalse()
            ->and(Driver::withTrashed()->sole()->trashed())->toBeTrue();

        app(UpdatePerson::class)->handle($person, personData(), driverData(['license_category' => 'C2']));

        expect(Driver::query()->sole()->getKey())->toBe($driver->getKey())
            ->and(Driver::query()->sole()->license_category)->toBe(LicenseCategory::C2);
    });

    it('validates the license data', function (): void {
        app(CreatePerson::class)->handle(personData(), driverData(['license_category' => 'Z9']));
    })->throws(ValidationException::class);

    it('rolls back the person when the driver data is invalid', function (): void {
        expect(fn () => app(CreatePerson::class)->handle(personData(), driverData(['license_number' => ''])))
            ->toThrow(ValidationException::class);

        expect(Person::query()->count())->toBe(0);
    });
});

describe('compliance of people', function (): void {
    beforeEach(function (): void {
        // Global types are seeded outside any company.
        CompanyContext::forget();
        app(DocumentTypeCatalogSeeder::class)->run();
        CompanyContext::activate($this->companyA);
    });

    it('demands the required person documents only from drivers', function (): void {
        $office = app(CreatePerson::class)->handle(personData(['position' => 'Auxiliar']));
        $driver = app(CreatePerson::class)->handle(personData(['document_number' => '99000222']), driverData());

        $compliance = app(DocumentCompliance::class);

        expect($compliance->forPerson($office)->status)->toBe(ComplianceStatus::Compliant);

        $report = $compliance->forPerson($driver);

        expect($report->status)->toBe(ComplianceStatus::NonCompliant)
            ->and(array_map(fn ($type): string => $type->code, $report->missing))->toEqualCanonicalizing(['person.driving_license', 'person.occupational_exam'])
            ->and($report->blocksOperation)->toBeTrue();

        expect(app(DocumentCompliance::class)->forPeople(Person::query()->get()))->toHaveCount(2);

        // The license is an expiring document of the person (morph alias "person").
        $license = DocumentType::query()->where('code', 'person.driving_license')->sole();
        $document = app(RegisterExpiringDocument::class)->handle($driver, $license, ['expires_at' => '2030-01-31']);

        expect($document->documentable_type)->toBe('person')
            ->and(array_map(fn ($type): string => $type->code, $compliance->forPerson($driver)->missing))->toBe(['person.occupational_exam'])
            ->and($compliance->forPerson($driver)->blocksOperation)->toBeFalse();
    });
});
