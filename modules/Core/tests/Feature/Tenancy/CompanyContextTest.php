<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use App\Support\Tenancy\MissingCompanyContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Enums\AuditEvent;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Models\AuditEntry;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Membership;
use Modules\Core\Models\ModuleLicense;
use Modules\Core\Models\User;

/*
 * Criterion 2: a query without an active company throws instead of
 * returning data.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedCore();

    $this->company = createCompany();
});

it('throws when company data is queried without an active company', function (string $model): void {
    expect(CompanyContext::check())->toBeFalse();

    $model::query()->get();
})->throws(MissingCompanyContext::class, 'No active company')->with([
    Branch::class,
    Membership::class,
    ModuleLicense::class,
    AuditEntry::class,
]);

it('throws when company data is created without an active company', function (): void {
    Branch::query()->create(['name' => 'Sin empresa', 'city' => 'Cali', 'address' => 'Calle 1']);
})->throws(MissingCompanyContext::class, 'cannot be saved without a company_id');

it('throws through relationships too', function (): void {
    $this->company->branches()->get();
})->throws(MissingCompanyContext::class);

it('scopes queries inside run() and restores the previous context', function (): void {
    $other = createCompany();

    CompanyContext::run($this->company, function () use ($other): void {
        expect(Branch::query()->count())->toBe(1);

        CompanyContext::run($other, fn () => expect(CompanyContext::id())->toBe($other->getKey()));

        expect(CompanyContext::id())->toBe($this->company->getKey());
    });

    expect(CompanyContext::check())->toBeFalse();
});

it('restores the context even when the callback fails', function (): void {
    try {
        CompanyContext::run($this->company, fn () => throw new RuntimeException('boom'));
    } catch (RuntimeException) {
        //
    }

    expect(CompanyContext::check())->toBeFalse();
});

it('clears the context set by a web request when the request ends', function (): void {
    $admin = createMember($this->company, CompanyRole::CompanyAdmin);

    $this->actingAs($admin)->get('/app/'.$this->company->getKey().'/branches')->assertOk();

    expect(CompanyContext::check())->toBeFalse();
});

it('lets only the platform administrator leave the company scope, and audits it', function (): void {
    $other = createCompany();
    $admin = User::factory()->platformAdmin()->create();

    $this->actingAs($admin);

    expect(Branch::query()->withoutCompanyScope()->count())->toBe(2)
        ->and(Branch::query()->withoutCompanyScope()->count())->toBe(2);

    // One entry per model and request, even if the scope is left twice.
    $entries = AuditEntry::query()->withoutCompanyScope()
        ->where('event', AuditEvent::ScopeBypassed->value)
        ->where('properties->model', 'Branch')
        ->get();

    expect($entries)->toHaveCount(1);

    $entry = $entries->first();

    expect($entry->causer_id)->toBe((string) $admin->getKey())
        ->and($entry->company_id)->toBeNull()
        ->and($entry->getProperty('model'))->toBe('Branch')
        ->and($other)->not->toBeNull();
});

it('forbids leaving the company scope to everybody else', function (): void {
    $user = createMember($this->company, CompanyRole::CompanyAdmin);

    $this->actingAs($user);

    Branch::query()->withoutCompanyScope()->get();
})->throws(AuthorizationException::class);

it('forbids leaving the company scope without a signed-in user', function (): void {
    Branch::query()->withoutCompanyScope()->get();
})->throws(AuthorizationException::class);
