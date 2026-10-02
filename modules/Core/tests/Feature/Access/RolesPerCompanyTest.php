<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Actions\SetMembershipStatus;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Models\Branch;

/*
 * Criterion 5: one user can have different roles in different companies.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedCore();

    $this->first = createCompany();
    $this->second = createCompany();

    $this->user = createMember($this->first, CompanyRole::CompanyAdmin, ['document_number' => '55555555']);
    addMembership($this->user, $this->second, CompanyRole::Viewer);
});

it('reads the roles of the active company only', function (): void {
    expect($this->user->roleNamesInCompany($this->first))->toBe(['company_admin'])
        ->and($this->user->roleNamesInCompany($this->second))->toBe(['viewer']);

    CompanyContext::run($this->first, fn () => expect($this->user->forgetCompanyRoles()->hasRole('company_admin'))->toBeTrue());
    CompanyContext::run($this->second, fn () => expect($this->user->forgetCompanyRoles()->hasRole('company_admin'))->toBeFalse());
});

it('authorises with the permissions of the active company', function (): void {
    CompanyContext::run($this->first, fn () => expect($this->user->forgetCompanyRoles()->can('create', Branch::class))->toBeTrue());
    CompanyContext::run($this->second, fn () => expect($this->user->forgetCompanyRoles()->can('create', Branch::class))->toBeFalse());

    expect($this->user->hasPermissionInCompany($this->first, 'core.users.create'))->toBeTrue()
        ->and($this->user->hasPermissionInCompany($this->second, 'core.users.create'))->toBeFalse()
        ->and($this->user->hasPermissionInCompany($this->second, 'core.users.view'))->toBeTrue();
});

it('shows each company panel with its own permissions', function (): void {
    $this->actingAs($this->user)
        ->get('/app/'.$this->first->getKey().'/audit')
        ->assertOk();

    $this->actingAs($this->user)
        ->get('/app/'.$this->second->getKey().'/audit')
        ->assertForbidden();

    $this->actingAs($this->user)
        ->get('/app/'.$this->second->getKey().'/branches')
        ->assertOk()
        ->assertDontSee('Nueva sede');
});

it('has no roles at all outside a company', function (): void {
    expect($this->user->forgetCompanyRoles()->getRoleNames())->toBeEmpty()
        ->and($this->user->checkPermissionTo('core.users.view'))->toBeFalse();
});

it('deactivating the user in one company keeps the other', function (): void {
    CompanyContext::run($this->first, fn () => app(SetMembershipStatus::class)->handle($this->user, false));

    expect($this->user->activeCompanies()->pluck('id')->all())->toBe([$this->second->getKey()]);

    $this->actingAs($this->user)->get('/app/'.$this->first->getKey())->assertNotFound();
    $this->actingAs($this->user)->get('/app/'.$this->second->getKey())->assertOk();
});
