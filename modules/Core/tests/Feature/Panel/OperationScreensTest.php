<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use Filament\Actions\EditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Database\Seeders\DocumentTypeCatalogSeeder;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Enums\PersonStatus;
use Modules\Core\Filament\Resources\Documents\Pages\ListExpiringDocuments;
use Modules\Core\Filament\Resources\DocumentTypes\Pages\ManageDocumentTypes;
use Modules\Core\Filament\Resources\People\Pages\CreatePerson;
use Modules\Core\Filament\Resources\People\Pages\EditPerson;
use Modules\Core\Filament\Resources\People\Pages\ListPeople;
use Modules\Core\Filament\Resources\Shared\DocumentsRelationManager;
use Modules\Core\Filament\Resources\Vehicles\Pages\EditVehicle;
use Modules\Core\Models\DocumentType;
use Modules\Core\Models\Driver;
use Modules\Core\Models\ExpiringDocument;
use Modules\Core\Models\Person;
use Modules\Core\Models\Vehicle;
use Spatie\Permission\Models\Role;

/*
 * The people, documents and document type screens: what each role sees and
 * does, isolation of record keys between companies (criterion 16) and the
 * read-only period of an expired license.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedCore();
    app(DocumentTypeCatalogSeeder::class)->run();

    $this->company = createCompany();
    $this->admin = createMember($this->company, CompanyRole::CompanyAdmin);
    actingInPanel($this->admin, $this->company);

    $this->soat = DocumentType::query()->where('code', 'vehicle.soat')->sole();
    $this->vehicle = Vehicle::factory()->create(['plate' => 'TST001']);
    $this->person = Person::factory()->create(['first_name' => 'Ana', 'last_name' => 'Prueba', 'document_number' => '99000123']);
});

afterEach(fn () => CompanyContext::forget());

describe('people', function (): void {
    it('lists, searches by name or document and filters by status', function (): void {
        $retired = Person::factory()->inactive()->create(['first_name' => 'Rita', 'last_name' => 'Retirada']);

        Livewire::test(ListPeople::class)
            ->assertCanSeeTableRecords([$this->person])
            ->assertCanNotSeeTableRecords([$retired])
            ->searchTable('99000123')
            ->assertCanSeeTableRecords([$this->person])
            ->searchTable('Rita')
            ->filterTable('status', PersonStatus::Inactive->value)
            ->assertCanSeeTableRecords([$retired]);
    });

    it('creates a driver with the person form', function (): void {
        Livewire::test(CreatePerson::class)
            ->fillForm([
                'document_type' => 'CC',
                'document_number' => '99.000.456',
                'first_name' => 'Beto',
                'last_name' => 'Prueba',
                'position' => 'Conductor',
                'is_driver' => true,
                'license_number' => '99000456',
                'license_category' => 'C3',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $person = Person::query()->where('document_number', '99000456')->sole();

        expect($person->driver?->license_category->value)->toBe('C3');
    });

    it('shows the duplicated document next to its field', function (): void {
        Livewire::test(CreatePerson::class)
            ->fillForm(['document_type' => 'CC', 'document_number' => '99000123', 'first_name' => 'X', 'last_name' => 'Y', 'position' => 'Z'])
            ->call('create')
            ->assertHasFormErrors(['document_number']);
    });

    it('keeps the driver profile when someone without driver rights edits the person', function (): void {
        Driver::factory()->for($this->person)->create(['license_category' => 'C2']);

        // PESV leaders may edit people and drivers; drop driver rights for this test.
        $leader = createMember($this->company, CompanyRole::PesvLeader);
        $leader->forgetCompanyRoles();
        Role::findByName('pesv_leader')->revokePermissionTo('core.drivers.update');
        actingInPanel($leader->forgetCompanyRoles(), $this->company);

        Livewire::test(EditPerson::class, ['record' => $this->person->getKey()])
            ->fillForm(['position' => 'Conductora líder'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($this->person->fresh()->position)->toBe('Conductora líder')
            ->and($this->person->fresh()->driver?->license_category->value)->toBe('C2');
    });

    it('creates the system access and shows the temporary password once', function (): void {
        Driver::factory()->for($this->person)->create();

        Livewire::test(ListPeople::class)
            ->callTableAction('createAccess', $this->person->getKey())
            ->assertHasNoTableActionErrors()
            ->assertNotified();

        expect($this->person->fresh()->user_id)->not->toBeNull();
    });

    it('retires and reactivates a person', function (): void {
        Livewire::test(ListPeople::class)->callTableAction('changeStatus', $this->person->getKey());

        expect($this->person->fresh()->status)->toBe(PersonStatus::Inactive);

        Livewire::test(ListPeople::class)
            ->filterTable('status', PersonStatus::Inactive->value)
            ->callTableAction('changeStatus', $this->person->getKey());

        expect($this->person->fresh()->status)->toBe(PersonStatus::Active);
    });
});

describe('documents', function (): void {
    beforeEach(function (): void {
        $this->expired = ExpiringDocument::factory()->of($this->vehicle, $this->soat)->expiringOn(now()->subDays(3)->toDateString())->create();
        $this->license = ExpiringDocument::factory()
            ->of($this->person, DocumentType::query()->where('code', 'person.driving_license')->sole())
            ->expiringOn(now()->addDays(10)->toDateString())
            ->create();
    });

    it('lists every document with status, holder and filters', function (): void {
        Livewire::test(ListExpiringDocuments::class)
            ->assertCanSeeTableRecords([$this->expired, $this->license])
            ->assertSee('TST001')
            ->assertSee('Ana Prueba')
            ->assertSee('Vencido')
            ->assertSee('Por vencer')
            ->filterTable('status', 'expired')
            ->assertCanSeeTableRecords([$this->expired])
            ->assertCanNotSeeTableRecords([$this->license]);

        Livewire::test(ListExpiringDocuments::class)
            ->filterTable('next_30_days', true)
            ->assertCanSeeTableRecords([$this->license])
            ->assertCanNotSeeTableRecords([$this->expired]);

        Livewire::test(ListExpiringDocuments::class)
            ->filterTable('documentable_type', 'vehicle')
            ->assertCanSeeTableRecords([$this->expired])
            ->assertCanNotSeeTableRecords([$this->license]);
    });

    it('shows the soonest expiry first and documents without expiry last', function (): void {
        $card = ExpiringDocument::factory()
            ->of($this->vehicle, DocumentType::query()->where('code', 'vehicle.registration_card')->sole())
            ->expiringOn(null)
            ->create();

        Livewire::test(ListExpiringDocuments::class)
            ->assertCanSeeTableRecords([$this->expired, $this->license, $card], inOrder: true);
    });

    it('renews a document from the list, keeping the previous one', function (): void {
        Livewire::test(ListExpiringDocuments::class)
            ->callTableAction('renew', $this->expired->getKey(), [
                'expires_at' => now()->addYear()->toDateString(),
                'files' => [],
            ])
            ->assertHasNoTableActionErrors();

        expect($this->expired->fresh()->is_current)->toBeFalse()
            ->and($this->vehicle->documents()->current()->sole()->expires_at->toDateString())->toBe(now()->addYear()->toDateString());
    });

    it('registers a document from the vehicle tab', function (): void {
        Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $this->vehicle, 'pageClass' => EditVehicle::class])
            ->callTableAction('register', data: [
                'document_type_id' => DocumentType::query()->where('code', 'vehicle.technical_inspection')->value('id'),
                'expires_at' => now()->addYear()->toDateString(),
                'files' => [],
            ])
            ->assertHasNoTableActionErrors();

        expect($this->vehicle->documents()->count())->toBe(2);
    });

    it('is read-only in the expired license period', function (): void {
        createLicense($this->company, ['module_code' => 'pesv', 'starts_at' => now()->subYear()->toDateString(), 'ends_at' => now()->subDays(3)->toDateString()]);

        Livewire::test(ListExpiringDocuments::class)
            ->assertCanSeeTableRecords([$this->expired])
            ->assertTableActionHidden('register')
            ->assertTableActionHidden('renew', $this->expired->getKey())
            ->assertTableActionHidden(EditAction::class, $this->expired->getKey())
            ->call('mountAction', 'renew', [], ['table' => true, 'recordKey' => $this->expired->getKey()])
            ->set('mountedActions.0.data', ['expires_at' => now()->addYear()->toDateString()])
            ->call('callMountedAction');

        expect($this->expired->fresh()->is_current)->toBeTrue()
            ->and(ExpiringDocument::query()->count())->toBe(2);

        Livewire::test(ListPeople::class)
            ->assertCanSeeTableRecords([$this->person])
            ->assertActionHidden('create')
            ->assertTableActionHidden('changeStatus', $this->person->getKey())
            ->assertTableActionHidden('createAccess', $this->person->getKey());
    });
});

describe('isolation of record keys (criterion 16)', function (): void {
    beforeEach(function (): void {
        $this->other = createCompany();

        [$this->foreignPerson, $this->foreignVehicle, $this->foreignDocument] = CompanyContext::run($this->other, function (): array {
            $person = Person::factory()->create();
            $vehicle = Vehicle::factory()->create(['plate' => 'TST900']);
            $document = ExpiringDocument::factory()->of($vehicle, $this->soat)->create();

            return [$person, $vehicle, $document];
        });
    });

    it('answers 404 to edit pages of another company records', function (): void {
        $base = '/app/'.$this->company->getKey();

        $this->get($base.'/people/'.$this->foreignPerson->getKey().'/edit')->assertNotFound();
        $this->get($base.'/vehicles/'.$this->foreignVehicle->getKey().'/edit')->assertNotFound();
        $this->get($base.'/documents')->assertOk()->assertDontSee('TST900');
    });

    it('rejects Livewire actions that receive the key of another company record', function (): void {
        Livewire::test(ListPeople::class)
            ->call('mountAction', 'changeStatus', [], ['table' => true, 'recordKey' => $this->foreignPerson->getKey()])
            ->call('callMountedAction');

        Livewire::test(ListExpiringDocuments::class)
            ->call('mountAction', 'renew', [], ['table' => true, 'recordKey' => $this->foreignDocument->getKey()])
            ->set('mountedActions.0.data', ['expires_at' => now()->addYear()->toDateString()])
            ->call('callMountedAction');

        CompanyContext::run($this->other, function (): void {
            expect($this->foreignPerson->fresh()->status)->toBe(PersonStatus::Active)
                ->and($this->foreignDocument->fresh()->is_current)->toBeTrue()
                ->and(ExpiringDocument::query()->count())->toBe(1);
        });
    });

    it('refuses to mount the edit page with a key of another company', function (): void {
        Livewire::test(EditPerson::class, ['record' => $this->foreignPerson->getKey()])->assertNotFound();
    });
});

describe('document types', function (): void {
    it('shows global and own types and lets the administrator manage only the own ones', function (): void {
        $own = DocumentType::factory()->create(['name' => 'Revisión de GPS']);

        Livewire::test(ManageDocumentTypes::class)
            ->assertCanSeeTableRecords([$this->soat, $own])
            ->assertTableActionHidden(EditAction::class, $this->soat->getKey())
            ->assertTableActionVisible(EditAction::class, $own->getKey())
            ->callAction('create', [
                'name' => 'Certificado de fumigación',
                'code' => 'empresa.fumigacion',
                'applies_to' => 'vehicle',
                'warning_days' => 15,
                'requires_expiry' => true,
            ])
            ->assertHasNoActionErrors();

        expect(DocumentType::query()->where('code', 'empresa.fumigacion')->sole()->company_id)->toBe($this->company->getKey());
    });

    it('does not repeat a visible code', function (): void {
        Livewire::test(ManageDocumentTypes::class)
            ->callAction('create', ['name' => 'Otro SOAT', 'code' => 'vehicle.soat', 'applies_to' => 'vehicle', 'warning_days' => 30])
            ->assertHasActionErrors(['code']);
    });

    it('is not available to other roles', function (): void {
        $leader = createMember($this->company, CompanyRole::PesvLeader);

        $this->actingAs($leader)->get('/app/'.$this->company->getKey().'/document-types')->assertForbidden();
    });
});
