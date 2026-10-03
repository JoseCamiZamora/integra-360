<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\DocumentCompliance;
use Modules\Core\Database\Seeders\DocumentTypeCatalogSeeder;
use Modules\Core\Enums\ComplianceStatus;
use Modules\Core\Enums\VehicleType;
use Modules\Core\Models\DocumentType;
use Modules\Core\Models\ExpiringDocument;
use Modules\Core\Models\Vehicle;

/*
 * Criterion 7: DocumentCompliance reports expired, expiring soon and
 * missing documents, and whether they block the operation.
 *
 * Seeded vehicle types: SOAT, technical inspection and operation card are
 * required and blocking; the registration card is required, not blocking
 * and has no expiry; the liability insurance is optional and blocking.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedCore();
    app(DocumentTypeCatalogSeeder::class)->run();

    $this->company = createCompany();
    CompanyContext::activate($this->company);

    $this->today = CarbonImmutable::parse('2026-10-03 09:00', 'America/Bogota');
    $this->travelTo($this->today);

    $this->type = fn (string $code): DocumentType => DocumentType::query()->where('code', $code)->sole();
    $this->compliance = app(DocumentCompliance::class);

    // Every required type in force, expiring in a year (registration card: no expiry).
    $this->complete = function (Vehicle $vehicle): Vehicle {
        foreach (['vehicle.soat', 'vehicle.technical_inspection', 'vehicle.operation_card'] as $code) {
            ExpiringDocument::factory()->of($vehicle, ($this->type)($code))->expiringOn('2027-10-01')->create();
        }

        ExpiringDocument::factory()->of($vehicle, ($this->type)('vehicle.registration_card'))->expiringOn(null)->create();

        return $vehicle;
    };

    $this->vehicle = ($this->complete)(Vehicle::factory()->create(['plate' => 'TST001']));

    $this->setSoat = fn (?string $expiresAt) => $this->vehicle->documents()
        ->where('document_type_id', ($this->type)('vehicle.soat')->getKey())
        ->update(['expires_at' => $expiresAt]);
});

afterEach(fn () => CompanyContext::forget());

it('is compliant when every required document is in force', function (): void {
    $report = $this->compliance->forVehicle($this->vehicle);

    expect($report->status)->toBe(ComplianceStatus::Compliant)
        ->and($report->isCompliant())->toBeTrue()
        ->and($report->expired)->toBe([])
        ->and($report->expiringSoon)->toBe([])
        ->and($report->missing)->toBe([])
        ->and($report->blocksOperation)->toBeFalse();
});

it('reports documents expiring soon without blocking', function (): void {
    ($this->setSoat)('2026-10-13');

    $report = $this->compliance->forVehicle($this->vehicle);

    expect($report->status)->toBe(ComplianceStatus::ExpiringSoon)
        ->and($report->expiringSoon)->toHaveCount(1)
        ->and($report->expiringSoon[0]->documentType->code)->toBe('vehicle.soat')
        ->and($report->blocksOperation)->toBeFalse();
});

it('still accepts a document on its last day and blocks it the next day', function (): void {
    ($this->setSoat)('2026-10-03');

    expect($this->compliance->forVehicle($this->vehicle)->status)->toBe(ComplianceStatus::ExpiringSoon);

    $tomorrow = $this->compliance->forVehicle($this->vehicle, $this->today->addDay()->startOfDay());

    expect($tomorrow->status)->toBe(ComplianceStatus::NonCompliant)
        ->and($tomorrow->expired[0]->documentType->code)->toBe('vehicle.soat')
        ->and($tomorrow->blocksOperation)->toBeTrue();
});

it('blocks the operation when a blocking document is missing', function (): void {
    $this->vehicle->documents()->where('document_type_id', ($this->type)('vehicle.technical_inspection')->getKey())->forceDelete();

    $report = $this->compliance->forVehicle($this->vehicle);

    expect($report->status)->toBe(ComplianceStatus::NonCompliant)
        ->and(array_map(fn (DocumentType $type): string => $type->code, $report->missing))->toBe(['vehicle.technical_inspection'])
        ->and($report->blocksOperation)->toBeTrue();
});

it('does not block when the missing required document does not block', function (): void {
    $this->vehicle->documents()->where('document_type_id', ($this->type)('vehicle.registration_card')->getKey())->forceDelete();

    $report = $this->compliance->forVehicle($this->vehicle);

    expect($report->status)->toBe(ComplianceStatus::NonCompliant)
        ->and($report->missing[0]->code)->toBe('vehicle.registration_card')
        ->and($report->blocksOperation)->toBeFalse();
});

it('does not demand optional documents, but an expired blocking one blocks', function (): void {
    expect($this->compliance->forVehicle($this->vehicle)->missing)->toBe([]);

    ExpiringDocument::factory()->of($this->vehicle, ($this->type)('vehicle.liability_insurance'))->expiringOn('2026-09-30')->create();

    $report = $this->compliance->forVehicle($this->vehicle);

    expect($report->status)->toBe(ComplianceStatus::NonCompliant)
        ->and($report->expired[0]->documentType->code)->toBe('vehicle.liability_insurance')
        ->and($report->blocksOperation)->toBeTrue();
});

it('only counts current documents, not the renewed ones', function (): void {
    ExpiringDocument::factory()->of($this->vehicle, ($this->type)('vehicle.soat'))->previous()->expiringOn('2025-10-01')->create();

    expect($this->compliance->forVehicle($this->vehicle)->status)->toBe(ComplianceStatus::Compliant);
});

it('ignores deleted documents, which become missing', function (): void {
    $this->vehicle->documents()->where('document_type_id', ($this->type)('vehicle.soat')->getKey())->sole()->delete();

    expect($this->compliance->forVehicle($this->vehicle)->missing[0]->code)->toBe('vehicle.soat');
});

it('applies only active types for the vehicle type, including the company own types', function (): void {
    DocumentType::factory()->required()->create(['code' => 'company.bus_only', 'vehicle_types' => [VehicleType::Bus->value]]);
    DocumentType::factory()->required()->create(['code' => 'company.inactive', 'is_active' => false]);
    $own = DocumentType::factory()->required()->create(['code' => 'company.gps', 'vehicle_types' => [VehicleType::Tractocamion->value]]);

    $otherCompany = createCompany();
    CompanyContext::run($otherCompany, fn () => DocumentType::factory()->required()->create(['code' => 'other.required']));

    $report = $this->compliance->forVehicle($this->vehicle);

    expect(array_map(fn (DocumentType $type): string => $type->code, $report->missing))->toBe([$own->code]);
});

it('evaluates many vehicles with two queries', function (): void {
    foreach (range(2, 6) as $i) {
        Vehicle::factory()->create(['plate' => sprintf('TST%03d', $i)]);
    }

    $vehicles = Vehicle::query()->get();

    DB::enableQueryLog();
    $reports = $this->compliance->forVehicles($vehicles);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(2)
        ->and($reports)->toHaveCount(6)
        ->and($reports[$this->vehicle->getKey()]->status)->toBe(ComplianceStatus::Compliant)
        ->and(collect($reports)->except($this->vehicle->getKey())->every(fn ($report): bool => $report->status === ComplianceStatus::NonCompliant && count($report->missing) === 4 && $report->blocksOperation))->toBeTrue();
});

it('has a label and a symbol tone for every overall status', function (): void {
    expect(ComplianceStatus::Compliant->statusLabel())->toBe('Al día')
        ->and(ComplianceStatus::ExpiringSoon->statusLabel())->toBe('Por vencer')
        ->and(ComplianceStatus::NonCompliant->statusLabel())->toBe('No cumple');
});
