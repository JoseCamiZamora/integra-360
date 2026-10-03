<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Actions\RegisterExpiringDocument;
use Modules\Core\Database\Seeders\DocumentTypeCatalogSeeder;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Models\Company;
use Modules\Core\Models\DocumentType;
use Modules\Core\Models\ExpiringDocument;
use Modules\Core\Models\ExpiringDocumentFile;
use Modules\Core\Models\User;
use Modules\Core\Models\Vehicle;

/*
 * Criteria 1 and 8: documents and their files are isolated by company and
 * only downloaded through a temporary signed URL after the policy allows it.
 * Files of sensitive types also need core.documents.view-sensitive.
 */

uses(RefreshDatabase::class);

/**
 * A document with one PDF for a vehicle of $company.
 */
function documentWithFile(Company $company, string $typeCode, string $plate): ExpiringDocumentFile
{
    return CompanyContext::run($company, function () use ($typeCode, $plate): ExpiringDocumentFile {
        $vehicle = Vehicle::factory()->create(['plate' => $plate]);
        $type = DocumentType::query()->where('code', $typeCode)->sole();

        return app(RegisterExpiringDocument::class)
            ->handle($vehicle, $type, ['expires_at' => '2027-06-30'], [uploadedFile('documento.pdf', pdfContent())])
            ->files()->sole();
    });
}

function fileUrl(Company $company, ExpiringDocumentFile $file): string
{
    return '/app/'.$company->getKey().'/documentos/archivos/'.$file->getKey();
}

beforeEach(function (): void {
    seedCore();
    app(DocumentTypeCatalogSeeder::class)->run();

    // The real local driver in a test folder: Storage::fake() replaces the
    // temporary signed URLs with unsigned ones.
    $disk = (string) config('filesystems.default');
    $root = storage_path('framework/testing/disks/documents');
    (new Filesystem)->ensureDirectoryExists($root);
    (new Filesystem)->cleanDirectory($root);
    config(["filesystems.disks.{$disk}.root" => $root]);
    Storage::forgetDisk($disk);

    $this->companyA = createCompany();
    $this->companyB = createCompany();
    $this->adminA = createMember($this->companyA, CompanyRole::CompanyAdmin);

    $this->fileA = documentWithFile($this->companyA, 'vehicle.soat', 'TST001');
    $this->fileB = documentWithFile($this->companyB, 'vehicle.soat', 'TST900');
});

describe('isolation between companies', function (): void {
    it('downloads an own file through a temporary signed URL', function (): void {
        $response = $this->actingAs($this->adminA)->get(fileUrl($this->companyA, $this->fileA));

        $response->assertRedirect();
        $target = (string) $response->headers->get('Location');

        expect($target)->toContain('signature=')->toContain('expires=');

        // The signed URL serves the private file...
        $this->get($target)->assertOk();

        // ...but the same path without the signature does not.
        $this->get(strtok($target, '?'))->assertForbidden();
    });

    it('answers 404 for a file of another company, even guessing its id', function (): void {
        $this->actingAs($this->adminA)
            ->get(fileUrl($this->companyA, $this->fileB))
            ->assertNotFound();

        $this->actingAs($this->adminA)
            ->get(fileUrl($this->companyB, $this->fileB))
            ->assertNotFound();
    });

    it('never finds documents or files of another company', function (): void {
        CompanyContext::run($this->companyA, function (): void {
            $documentB = $this->fileB->expiring_document_id;

            expect(ExpiringDocument::query()->find($documentB))->toBeNull()
                ->and(ExpiringDocumentFile::query()->find($this->fileB->getKey()))->toBeNull()
                ->and(ExpiringDocument::query()->whereKey($documentB)->update(['number' => 'X']))->toBe(0)
                ->and(ExpiringDocument::query()->count())->toBe(1)
                ->and($this->adminA->can('view', $this->fileB))->toBeFalse();
        });
    });

    it('stores each company files under its own folder', function (): void {
        expect($this->fileA->path)->toStartWith('companies/'.$this->companyA->getKey().'/')
            ->and($this->fileB->path)->toStartWith('companies/'.$this->companyB->getKey().'/');
    });

    it('does not let the platform administrator download company files', function (): void {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($platformAdmin)
            ->get(fileUrl($this->companyA, $this->fileA))
            ->assertForbidden();

        CompanyContext::run($this->companyA, function () use ($platformAdmin): void {
            expect($platformAdmin->can('view', $this->fileA))->toBeFalse()
                ->and($platformAdmin->can('view', $this->fileA->document))->toBeFalse();
        });
    });
});

describe('permissions', function (): void {
    beforeEach(function (): void {
        $this->examFile = documentWithFile($this->companyA, 'vehicle.operation_card', 'TST002');

        // Mark that type as sensitive for this test (a global type, edited outside any company).
        DocumentType::query()->globalOnly()->where('code', 'vehicle.operation_card')->sole()->update(['is_sensitive' => true]);
    });

    it('requires core.documents.view-sensitive for sensitive files', function (CompanyRole $role, bool $regular, bool $sensitive): void {
        $user = createMember($this->companyA, $role);

        $this->actingAs($user)->get(fileUrl($this->companyA, $this->fileA))
            ->assertStatus($regular ? 302 : 403);

        $this->actingAs($user)->get(fileUrl($this->companyA, $this->examFile))
            ->assertStatus($sensitive ? 302 : 403);
    })->with([
        'company administrator' => [CompanyRole::CompanyAdmin, true, true],
        'PESV leader' => [CompanyRole::PesvLeader, true, true],
        'management' => [CompanyRole::Management, true, true],
        'area manager' => [CompanyRole::AreaManager, true, false],
        'read only' => [CompanyRole::Viewer, true, false],
    ]);

    it('does not serve files to drivers through the panel', function (): void {
        $driver = createMember($this->companyA, CompanyRole::Driver);

        // Drivers have no panel access at all.
        $this->actingAs($driver)->get(fileUrl($this->companyA, $this->fileA))->assertForbidden();
    });

    it('does not serve files of deleted documents', function (): void {
        CompanyContext::run($this->companyA, fn () => $this->fileA->document->delete());

        $this->actingAs($this->adminA)->get(fileUrl($this->companyA, $this->fileA))->assertForbidden();
    });

    it('requires the right permissions on documents', function (): void {
        $viewer = createMember($this->companyA, CompanyRole::Viewer);
        $leader = createMember($this->companyA, CompanyRole::PesvLeader);

        CompanyContext::run($this->companyA, function () use ($viewer, $leader): void {
            $document = $this->fileA->document;

            expect($viewer->can('view', $document))->toBeTrue()
                ->and($viewer->can('create', ExpiringDocument::class))->toBeFalse()
                ->and($viewer->can('renew', $document))->toBeFalse()
                ->and($viewer->can('delete', $document))->toBeFalse()
                ->and($leader->can('renew', $document))->toBeTrue()
                ->and($leader->can('delete', $document))->toBeTrue()
                ->and($leader->can('forceDelete', $document))->toBeFalse();
        });
    });
});

afterEach(fn () => (new Filesystem)->deleteDirectory(storage_path('framework/testing/disks/documents')));
