<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Database\Seeders\DocumentTypeCatalogSeeder;
use Modules\Core\Database\Seeders\LicenseCategoryEquivalenceSeeder;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Filament\Platform\Resources\DocumentTypes\Pages\ManageGlobalDocumentTypes;
use Modules\Core\Filament\Platform\Resources\LicenseCategories\Pages\ManageLicenseCategories;
use Modules\Core\Models\DocumentType;
use Modules\Core\Models\User;
use Modules\Core\Models\VehicleTypeLicenseCategory;

/*
 * Platform panel: global document types and the license categories by
 * vehicle type, managed only by the platform administrator.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedCore();
    app(DocumentTypeCatalogSeeder::class)->run();
    app(LicenseCategoryEquivalenceSeeder::class)->run();

    actingInPlatform(User::factory()->create(['is_platform_admin' => true]));

    $this->soat = DocumentType::query()->globalOnly()->where('code', 'vehicle.soat')->sole();
});

it('creates and edits global document types', function (): void {
    Livewire::test(ManageGlobalDocumentTypes::class)
        ->assertCanSeeTableRecords([$this->soat])
        ->callAction('create', [
            'name' => 'Certificado de prueba global',
            'code' => 'vehicle.test_global',
            'applies_to' => 'vehicle',
            'warning_days' => 20,
        ])
        ->assertHasNoActionErrors()
        ->callTableAction(EditAction::class, $this->soat->getKey(), [
            'name' => 'SOAT',
            'code' => 'vehicle.soat',
            'applies_to' => 'vehicle',
            'warning_days' => 45,
        ])
        ->assertHasNoTableActionErrors();

    $created = DocumentType::query()->globalOnly()->where('code', 'vehicle.test_global')->sole();

    expect($created->isGlobal())->toBeTrue()
        ->and($this->soat->fresh()->warning_days)->toBe(45);
});

it('never shows company types in the platform list', function (): void {
    $company = createCompany();
    $own = CompanyContext::run($company, fn (): DocumentType => DocumentType::factory()->create());

    Livewire::test(ManageGlobalDocumentTypes::class)
        ->assertCanSeeTableRecords([$this->soat])
        ->assertCanNotSeeTableRecords([$own]);
});

it('edits the license categories by vehicle type', function (): void {
    $pair = VehicleTypeLicenseCategory::query()->where('vehicle_type', 'tractocamion')->where('license_category', 'C3')->sole();

    Livewire::test(ManageLicenseCategories::class)
        ->callAction('create', ['vehicle_type' => 'tractocamion', 'license_category' => 'C2'])
        ->assertHasNoActionErrors()
        ->callAction('create', ['vehicle_type' => 'tractocamion', 'license_category' => 'C2'])
        ->assertHasActionErrors(['license_category']);

    Livewire::test(ManageLicenseCategories::class)
        ->callTableAction(DeleteAction::class, $pair->getKey());

    expect(VehicleTypeLicenseCategory::query()->where('vehicle_type', 'tractocamion')->pluck('license_category')->map->value->sort()->values()->all())
        ->toBe(['B3', 'C2']);
});

it('keeps both screens away from company users', function (): void {
    $company = createCompany();
    $admin = createMember($company, CompanyRole::CompanyAdmin);

    $this->actingAs($admin)->get('/plataforma/document-types')->assertForbidden();
    $this->actingAs($admin)->get('/plataforma/license-categories')->assertForbidden();
});
