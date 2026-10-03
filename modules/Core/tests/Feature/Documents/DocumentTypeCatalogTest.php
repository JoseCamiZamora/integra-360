<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use App\Support\Tenancy\MissingCompanyContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Database\Seeders\DocumentTypeCatalogSeeder;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Enums\DocumentAppliesTo;
use Modules\Core\Models\DocumentType;
use Modules\Core\Models\User;

/*
 * Configurable catalog of document types: global types (company_id NULL)
 * plus each company's own, never another company's (criterion 1), and
 * global types managed only by the platform administrator.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedCore();
    app(DocumentTypeCatalogSeeder::class)->run();

    $this->companyA = createCompany();
    $this->companyB = createCompany();

    $this->ownA = CompanyContext::run($this->companyA, fn (): DocumentType => DocumentType::factory()->create(['name' => 'Propio de A']));
    $this->ownB = CompanyContext::run($this->companyB, fn (): DocumentType => DocumentType::factory()->create(['name' => 'Propio de B']));
});

describe('scope', function (): void {
    it('shows the global types plus the active company types, never another company types', function (): void {
        $names = CompanyContext::run($this->companyA, fn () => DocumentType::query()->pluck('name'));

        expect($names)->toContain('SOAT', 'Licencia de conducción', 'Propio de A')
            ->not->toContain('Propio de B')
            ->and($names)->toHaveCount(count(DocumentTypeCatalogSeeder::TYPES) + 1);

        CompanyContext::run($this->companyA, function (): void {
            expect(DocumentType::query()->find($this->ownB->getKey()))->toBeNull()
                ->and(DocumentType::query()->whereKey($this->ownB->getKey())->update(['name' => 'Cambiado']))->toBe(0);
        });
    });

    it('fails safe without an active company', function (): void {
        DocumentType::query()->count();
    })->throws(MissingCompanyContext::class);

    it('lists only global types outside a company (platform)', function (): void {
        expect(DocumentType::query()->globalOnly()->count())->toBe(count(DocumentTypeCatalogSeeder::TYPES));
    });

    it('creates company types inside a company and global types only explicitly', function (): void {
        expect($this->ownA->company_id)->toBe($this->companyA->getKey())
            ->and($this->ownA->isGlobal())->toBeFalse();

        expect(fn () => DocumentType::factory()->create())->toThrow(MissingCompanyContext::class);

        expect(fn () => CompanyContext::run($this->companyA, fn () => DocumentType::createGlobal(DocumentType::factory()->raw())))
            ->toThrow(MissingCompanyContext::class);
    });

    it('cannot change or delete a global type from inside a company', function (): void {
        CompanyContext::run($this->companyA, function (): void {
            $soat = DocumentType::query()->where('code', 'vehicle.soat')->sole();

            expect(fn () => $soat->update(['warning_days' => 5]))->toThrow(MissingCompanyContext::class)
                ->and(fn () => $soat->delete())->toThrow(MissingCompanyContext::class);
        });

        expect(DocumentType::query()->globalOnly()->where('code', 'vehicle.soat')->value('warning_days'))->toBe(30);
    });

    it('keeps codes unique among global types and within each company', function (): void {
        // The same code in two companies, and a company code equal to a global one, are allowed.
        CompanyContext::run($this->companyB, fn () => DocumentType::factory()->create(['code' => $this->ownA->code]));
        CompanyContext::run($this->companyA, fn () => DocumentType::factory()->create(['code' => 'vehicle.soat']));

        expect(fn () => DocumentType::createGlobal(DocumentType::factory()->raw(['code' => 'vehicle.soat'])))
            ->toThrow(QueryException::class)
            ->and(fn () => CompanyContext::run($this->companyA, fn () => DocumentType::factory()->create(['code' => $this->ownA->code])))
            ->toThrow(QueryException::class);
    });
});

describe('seeded catalog', function (): void {
    it('seeds the global types once, without touching edited ones', function (): void {
        DocumentType::query()->globalOnly()->where('code', 'vehicle.soat')->sole()->update(['warning_days' => 45]);

        app(DocumentTypeCatalogSeeder::class)->run();

        $soat = DocumentType::query()->globalOnly()->where('code', 'vehicle.soat')->sole();

        expect(DocumentType::query()->globalOnly()->count())->toBe(count(DocumentTypeCatalogSeeder::TYPES))
            ->and($soat->warning_days)->toBe(45)
            ->and($soat->is_required)->toBeTrue()
            ->and($soat->blocks_operation)->toBeTrue();

        $exam = DocumentType::query()->globalOnly()->where('code', 'person.occupational_exam')->sole();

        expect($exam->applies_to)->toBe(DocumentAppliesTo::Person)
            ->and($exam->is_sensitive)->toBeTrue();

        expect(DocumentType::query()->globalOnly()->where('code', 'vehicle.registration_card')->value('requires_expiry'))->toBeFalse();
    });
});

describe('who manages the types', function (): void {
    beforeEach(function (): void {
        $this->platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $this->adminA = createMember($this->companyA, CompanyRole::CompanyAdmin);
        $this->leaderA = createMember($this->companyA, CompanyRole::PesvLeader);
        $this->soat = DocumentType::query()->globalOnly()->where('code', 'vehicle.soat')->sole();
    });

    it('lets only the platform administrator edit global types', function (): void {
        // Platform panel: no company context.
        expect($this->platformAdmin->can('update', $this->soat))->toBeTrue()
            ->and($this->platformAdmin->can('create', DocumentType::class))->toBeTrue()
            ->and($this->platformAdmin->can('delete', $this->soat))->toBeFalse();

        CompanyContext::run($this->companyA, function (): void {
            expect($this->adminA->can('view', $this->soat))->toBeTrue()
                ->and($this->adminA->can('update', $this->soat))->toBeFalse()
                ->and($this->leaderA->can('update', $this->soat))->toBeFalse();
        });
    });

    it('lets the company administrator manage only their company types', function (): void {
        CompanyContext::run($this->companyA, function (): void {
            expect($this->adminA->can('create', DocumentType::class))->toBeTrue()
                ->and($this->adminA->can('update', $this->ownA))->toBeTrue()
                ->and($this->adminA->can('view', $this->ownB))->toBeFalse()
                ->and($this->adminA->can('update', $this->ownB))->toBeFalse()
                ->and($this->adminA->can('delete', $this->ownA))->toBeFalse()
                ->and($this->leaderA->can('create', DocumentType::class))->toBeFalse()
                ->and($this->leaderA->can('update', $this->ownA))->toBeFalse();
        });
    });

    it('does not let the platform administrator edit a company own type', function (): void {
        expect($this->platformAdmin->can('update', $this->ownA))->toBeFalse()
            ->and($this->platformAdmin->can('view', $this->ownA))->toBeFalse();
    });
});
