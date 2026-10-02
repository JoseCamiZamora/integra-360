<?php

declare(strict_types=1);

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Filament\Resources\Branches\Pages\ManageBranches;
use Modules\Core\Models\Branch;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedCore();

    $this->company = createCompany(['city' => 'Funza', 'address' => 'Calle 13 # 1-1']);
    $this->admin = createMember($this->company, CompanyRole::CompanyAdmin);

    actingInPanel($this->admin, $this->company);
});

it('creates every company with one main branch', function (): void {
    $main = Branch::query()->sole();

    expect($main->is_main)->toBeTrue()
        ->and($main->is_active)->toBeTrue()
        ->and($main->name)->toBe('Sede principal')
        ->and($main->city)->toBe('Funza');
});

it('creates, edits and deletes branches of the active company', function (): void {
    Livewire::test(ManageBranches::class)
        ->callAction('create', ['name' => 'Patio Mosquera', 'city' => 'Mosquera', 'address' => 'Km 2 vía Funza', 'is_active' => true])
        ->assertHasNoActionErrors();

    $branch = Branch::query()->where('name', 'Patio Mosquera')->sole();

    expect($branch->company_id)->toBe($this->company->getKey())
        ->and($branch->is_main)->toBeFalse();

    Livewire::test(ManageBranches::class)
        ->callTableAction(EditAction::class, $branch->getKey(), ['name' => 'Patio Mosquera 2', 'city' => 'Mosquera', 'address' => 'Km 2'])
        ->assertHasNoTableActionErrors();

    expect($branch->fresh()->name)->toBe('Patio Mosquera 2');

    Livewire::test(ManageBranches::class)->callTableAction(DeleteAction::class, $branch->getKey());

    expect(Branch::query()->count())->toBe(1);
});

it('moves the main mark and never deletes the main branch', function (): void {
    $main = Branch::query()->sole();
    $other = Branch::factory()->create();

    Livewire::test(ManageBranches::class)
        ->assertTableActionHidden(DeleteAction::class, $main->getKey())
        ->assertTableActionHidden('makeMain', $main->getKey())
        ->callTableAction('makeMain', $other->getKey());

    expect($other->fresh()->is_main)->toBeTrue()
        ->and($main->fresh()->is_main)->toBeFalse()
        ->and(Branch::query()->where('is_main', true)->count())->toBe(1);
});

it('enforces a single main branch in the database', function (): void {
    $other = Branch::factory()->create();
    $other->is_main = true;

    $other->save();
})->throws(QueryException::class);

it('lets read-only roles list branches without changing them', function (): void {
    $viewer = createMember($this->company, CompanyRole::Viewer);
    actingInPanel($viewer->forgetCompanyRoles(), $this->company);

    $main = Branch::query()->sole();

    Livewire::test(ManageBranches::class)
        ->assertCanSeeTableRecords([$main])
        ->assertActionHidden('create')
        ->assertTableActionHidden(EditAction::class, $main->getKey());
});

it('lets only the company administrator edit "Mi empresa"', function (): void {
    $this->get('/app/'.$this->company->getKey().'/profile')->assertOk()->assertSee('Mi empresa');

    $viewer = createMember($this->company, CompanyRole::Viewer);

    // Filament does not reveal the page: 404.
    $this->actingAs($viewer)->get('/app/'.$this->company->getKey().'/profile')->assertNotFound();
});
