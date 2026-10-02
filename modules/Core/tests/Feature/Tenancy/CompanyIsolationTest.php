<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Filament\Resources\AuditEntries\Pages\ListAuditEntries;
use Modules\Core\Filament\Resources\Branches\Pages\ManageBranches;
use Modules\Core\Filament\Resources\Users\Pages\ManageUsers;
use Modules\Core\Models\AuditEntry;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Membership;
use Modules\Core\Models\ModuleLicense;
use Modules\Core\Models\User;

/*
 * Criterion 1: a user of company A can neither see, edit nor guess by URL
 * the data of company B. Checked for every Core resource: list, detail,
 * edit and delete.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedCore();

    $this->companyA = createCompany(['legal_name' => 'Empresa A S.A.S.']);
    $this->companyB = createCompany(['legal_name' => 'Empresa B S.A.S.']);

    $this->adminA = createMember($this->companyA, CompanyRole::CompanyAdmin, ['name' => 'Admin de A']);
    $this->userB = createMember($this->companyB, CompanyRole::Viewer, ['name' => 'Usuario de B']);

    $this->branchB = CompanyContext::run($this->companyB, fn (): Branch => Branch::factory()->create(['name' => 'Patio secreto de B']));
    createLicense($this->companyB);
});

describe('URLs of another company', function (): void {
    it('answers 404 for any page of company B', function (string $page): void {
        $this->actingAs($this->adminA)
            ->get('/app/'.$this->companyB->getKey().$page)
            ->assertNotFound();
    })->with(['', '/branches', '/users', '/audit', '/profile']);

    it('only shows company A in company A pages', function (): void {
        $this->actingAs($this->adminA)
            ->get('/app/'.$this->companyA->getKey().'/branches')
            ->assertOk()
            ->assertDontSee('Patio secreto de B');

        $this->actingAs($this->adminA)
            ->get('/app/'.$this->companyA->getKey().'/users')
            ->assertOk()
            ->assertSee('Admin de A')
            ->assertDontSee('Usuario de B');
    });
});

describe('branches', function (): void {
    it('lists only the active company branches', function (): void {
        actingInPanel($this->adminA, $this->companyA);

        $own = Branch::query()->get();

        Livewire::test(ManageBranches::class)
            ->assertCanSeeTableRecords($own)
            ->assertCanNotSeeTableRecords([$this->branchB]);
    });

    it('cannot find, edit or delete a branch of another company', function (): void {
        actingInPanel($this->adminA, $this->companyA);

        expect(Branch::query()->find($this->branchB->getKey()))->toBeNull()
            ->and(Branch::query()->whereKey($this->branchB->getKey())->update(['name' => 'Hackeada']))->toBe(0)
            ->and(Branch::query()->whereKey($this->branchB->getKey())->delete())->toBe(0)
            ->and($this->adminA->can('view', $this->branchB))->toBeFalse()
            ->and($this->adminA->can('update', $this->branchB))->toBeFalse()
            ->and($this->adminA->can('delete', $this->branchB))->toBeFalse();

        // Guessing the key in a Livewire request: the record is not found.
        foreach ([EditAction::class, DeleteAction::class, 'makeMain'] as $action) {
            expect(fn () => Livewire::test(ManageBranches::class)->callTableAction($action, $this->branchB->getKey(), ['name' => 'Hackeada']))
                ->toThrow(ActionNotResolvableException::class, 'no longer exists');
        }

        expect(CompanyContext::run($this->companyB, fn (): ?string => Branch::query()->find($this->branchB->getKey())?->name))
            ->toBe('Patio secreto de B');
    });

    it('refuses to save a record into another company', function (): void {
        actingInPanel($this->adminA, $this->companyA);

        $branch = new Branch(['name' => 'Intrusa', 'city' => 'Cali', 'address' => 'Calle 1']);
        $branch->company_id = $this->companyB->getKey();

        $branch->save();
    })->throws(LogicException::class, 'belongs to another company');

    it('refuses to move a record to another company', function (): void {
        $branch = CompanyContext::run($this->companyA, fn (): Branch => Branch::factory()->create());

        CompanyContext::run($this->companyA, function () use ($branch): void {
            $branch->company_id = $this->companyB->getKey();
            $branch->save();
        });
    })->throws(LogicException::class, 'belongs to another company');
});

describe('users', function (): void {
    it('lists only the members of the active company', function (): void {
        actingInPanel($this->adminA, $this->companyA);

        Livewire::test(ManageUsers::class)
            ->assertCanSeeTableRecords([$this->adminA])
            ->assertCanNotSeeTableRecords([$this->userB]);
    });

    it('cannot view, edit, deactivate or reset a user of another company', function (): void {
        actingInPanel($this->adminA, $this->companyA);

        expect(User::query()->inActiveCompany()->find($this->userB->getKey()))->toBeNull()
            ->and($this->adminA->can('view', $this->userB))->toBeFalse()
            ->and($this->adminA->can('update', $this->userB))->toBeFalse()
            ->and($this->adminA->can('deactivate', $this->userB))->toBeFalse()
            ->and($this->adminA->can('resetPassword', $this->userB))->toBeFalse()
            ->and(Membership::query()->where('user_id', $this->userB->getKey())->exists())->toBeFalse();

        foreach ([EditAction::class, 'deactivate', 'resetPassword'] as $action) {
            expect(fn () => Livewire::test(ManageUsers::class)->callTableAction($action, $this->userB->getKey(), ['name' => 'Hackeado']))
                ->toThrow(ActionNotResolvableException::class, 'no longer exists');
        }

        expect($this->userB->fresh()?->name)->toBe('Usuario de B');
    });
});

describe('licenses and audit log', function (): void {
    it('never shows licenses of another company', function (): void {
        CompanyContext::run($this->companyA, function (): void {
            expect(ModuleLicense::query()->count())->toBe(0);
        });

        CompanyContext::run($this->companyB, function (): void {
            expect(ModuleLicense::query()->count())->toBe(1);
        });
    });

    it('lists only the audit entries of the active company', function (): void {
        actingInPanel($this->adminA, $this->companyA);

        $foreign = CompanyContext::run($this->companyB, fn () => AuditEntry::query()->get());
        $own = AuditEntry::query()->get();

        expect($foreign)->not->toBeEmpty()
            ->and($own->pluck('company_id')->unique()->all())->toBe([$this->companyA->getKey()]);

        Livewire::test(ListAuditEntries::class)
            ->assertCanSeeTableRecords($own)
            ->assertCanNotSeeTableRecords($foreign);

        expect($this->adminA->can('view', $foreign->first()))->toBeFalse();
    });
});

describe('company profile', function (): void {
    it('lets the company administrator edit only the active company', function (): void {
        actingInPanel($this->adminA, $this->companyA);

        expect($this->adminA->can('update', $this->companyA))->toBeTrue()
            ->and($this->adminA->can('update', $this->companyB))->toBeFalse()
            ->and($this->adminA->can('view', $this->companyB))->toBeFalse()
            ->and($this->adminA->can('viewAny', $this->companyA::class))->toBeFalse();
    });
});
