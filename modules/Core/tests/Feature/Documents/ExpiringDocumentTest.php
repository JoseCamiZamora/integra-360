<?php

declare(strict_types=1);

use App\Support\Tenancy\CompanyContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Core\Actions\RegisterExpiringDocument;
use Modules\Core\Actions\RenewExpiringDocument;
use Modules\Core\Database\Seeders\DocumentTypeCatalogSeeder;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Enums\DocumentStatus;
use Modules\Core\Enums\VehicleType;
use Modules\Core\Events\ExpiringDocumentRegistered;
use Modules\Core\Events\ExpiringDocumentRenewed;
use Modules\Core\Models\AuditEntry;
use Modules\Core\Models\DocumentType;
use Modules\Core\Models\ExpiringDocument;
use Modules\Core\Models\Vehicle;

/*
 * Registering and renewing expiring documents (criteria 5, 9 and 12).
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedCore();
    app(DocumentTypeCatalogSeeder::class)->run();
    Storage::fake();

    $this->company = createCompany();
    $this->admin = createMember($this->company, CompanyRole::CompanyAdmin);
    $this->actingAs($this->admin);
    CompanyContext::activate($this->company);

    $this->vehicle = Vehicle::factory()->create(['plate' => 'TST001']);
    $this->soat = DocumentType::query()->where('code', 'vehicle.soat')->sole();

    $this->register = fn (array $data = [], array $files = [], ?DocumentType $type = null): ExpiringDocument => app(RegisterExpiringDocument::class)
        ->handle($this->vehicle, $type ?? $this->soat, array_merge([
            'number' => 'AT-1234567',
            'issuer' => 'Aseguradora de prueba',
            'issued_at' => '2026-01-15',
            'expires_at' => '2027-01-14',
        ], $data), $files);
});

afterEach(fn () => CompanyContext::forget());

describe('registering', function (): void {
    it('registers a document with its files in the company folder', function (): void {
        Event::fake([ExpiringDocumentRegistered::class]);

        $document = ($this->register)([], [
            uploadedFile('soat.pdf', pdfContent()),
            uploadedFile('foto-soat.png', pngContent()),
        ]);

        expect($document->is_current)->toBeTrue()
            ->and($document->company_id)->toBe($this->company->getKey())
            ->and($document->documentable_type)->toBe('vehicle')
            ->and($document->expires_at?->toDateString())->toBe('2027-01-14')
            ->and($document->files)->toHaveCount(2);

        $pdf = $document->files->firstWhere('original_name', 'soat.pdf');

        expect($pdf->mime_type)->toBe('application/pdf')
            ->and($pdf->path)->toStartWith('companies/'.$this->company->getKey().'/documents/'.$document->getKey().'/')
            ->and($pdf->path)->toEndWith('.pdf')
            ->and($pdf->path)->not->toContain('soat');

        Storage::assertExists($pdf->path);

        Event::assertDispatched(ExpiringDocumentRegistered::class, fn (ExpiringDocumentRegistered $event): bool => $event->document->is($document)
            && $event->documentable->is($this->vehicle)
            && $event->companyId === $this->company->getKey());
    });

    it('refuses a second current document of the same type: it must be renewed', function (): void {
        ($this->register)();
        ($this->register)(['expires_at' => '2028-01-14']);
    })->throws(ValidationException::class, 'Use «Renovar»');

    it('refuses types that do not apply to the entity', function (): void {
        $license = DocumentType::query()->where('code', 'person.driving_license')->sole();

        expect(fn () => ($this->register)([], [], $license))->toThrow(ValidationException::class);

        $onlyBuses = DocumentType::factory()->create(['vehicle_types' => [VehicleType::Bus->value]]);

        expect(fn () => ($this->register)([], [], $onlyBuses))->toThrow(ValidationException::class);

        $inactive = DocumentType::factory()->create(['is_active' => false]);

        expect(fn () => ($this->register)([], [], $inactive))->toThrow(ValidationException::class);
    });

    it('requires the expiry date only when the type requires it', function (): void {
        expect(fn () => ($this->register)(['expires_at' => null]))->toThrow(ValidationException::class)
            ->and(fn () => ($this->register)(['issued_at' => '2026-05-01', 'expires_at' => '2026-04-30']))->toThrow(ValidationException::class);

        $card = DocumentType::query()->where('code', 'vehicle.registration_card')->sole();
        $document = ($this->register)(['expires_at' => '2030-01-01'], [], $card);

        expect($document->expires_at)->toBeNull()
            ->and($document->status())->toBe(DocumentStatus::NoExpiry);
    });

    it('enforces a single current document per entity and type in the database', function (): void {
        ExpiringDocument::factory()->of($this->vehicle, $this->soat)->create();
        ExpiringDocument::factory()->of($this->vehicle, $this->soat)->create();
    })->throws(QueryException::class);
});

describe('files', function (): void {
    it('rejects files whose content is not PDF, JPG or PNG, whatever their extension', function (string $name, string $content): void {
        expect(fn () => ($this->register)([], [uploadedFile($name, $content)]))->toThrow(ValidationException::class);

        expect(ExpiringDocument::query()->count())->toBe(0);
        Storage::assertDirectoryEmpty('/');
    })->with([
        'script renamed to .jpg' => ['foto.jpg', '<?php echo "hola"; ?>'],
        'text renamed to .pdf' => ['soat.pdf', 'esto no es un PDF'],
        'html renamed to .png' => ['imagen.png', '<html><body>no</body></html>'],
        'zip renamed to .pdf' => ['soat.pdf', "PK\x03\x04".str_repeat("\0", 30)],
    ]);

    it('rejects files larger than the configured limit', function (): void {
        expect(config('integra.documents.max_file_kb'))->toBe(10240);

        // A small limit keeps the test light; the rule reads the same setting.
        config(['integra.documents.max_file_kb' => 20]);

        ($this->register)([], [uploadedFile('soat.pdf', pdfContent().str_repeat('0', 19 * 1024))]);

        expect(fn () => ($this->register)(['expires_at' => '2028-01-01'], [uploadedFile('soat.pdf', pdfContent().str_repeat('0', 21 * 1024))]))
            ->toThrow(ValidationException::class, 'kilobytes');
    });

    it('accepts at most four files per document', function (): void {
        $files = array_map(fn (int $i) => uploadedFile("hoja-{$i}.pdf", pdfContent()), range(1, 5));

        ($this->register)([], $files);
    })->throws(ValidationException::class);
});

describe('renewing', function (): void {
    it('creates a new current document and keeps the previous one with its files', function (): void {
        Event::fake([ExpiringDocumentRenewed::class]);

        $first = ($this->register)([], [uploadedFile('soat-2026.pdf', pdfContent())]);

        $renewed = app(RenewExpiringDocument::class)->handle($first, [
            'number' => 'AT-7654321',
            'issued_at' => '2027-01-10',
            'expires_at' => '2028-01-09',
        ], [uploadedFile('soat-2027.pdf', pdfContent())]);

        $first->refresh();

        expect($renewed->is_current)->toBeTrue()
            ->and($renewed->getKey())->not->toBe($first->getKey())
            ->and($renewed->number)->toBe('AT-7654321')
            ->and($renewed->documentable_id)->toBe($this->vehicle->getKey())
            ->and($first->is_current)->toBeFalse()
            ->and($first->number)->toBe('AT-1234567')
            ->and($first->expires_at?->toDateString())->toBe('2027-01-14')
            ->and($first->files)->toHaveCount(1)
            ->and($renewed->files)->toHaveCount(1)
            ->and($this->vehicle->documents()->count())->toBe(2)
            ->and($this->vehicle->documents()->current()->sole()->is($renewed))->toBeTrue();

        Storage::assertExists($first->files->sole()->path);

        Event::assertDispatched(ExpiringDocumentRenewed::class, fn (ExpiringDocumentRenewed $event): bool => $event->document->is($renewed)
            && $event->previous->is($first)
            && ! $event->previous->is_current
            && $event->documentable->is($this->vehicle));
    });

    it('only renews the current document', function (): void {
        $first = ($this->register)();
        app(RenewExpiringDocument::class)->handle($first, ['expires_at' => '2028-01-09']);

        app(RenewExpiringDocument::class)->handle($first->refresh(), ['expires_at' => '2029-01-09']);
    })->throws(ValidationException::class);

    it('lets the document be renewed after the previous renewal', function (): void {
        $first = ($this->register)();
        $second = app(RenewExpiringDocument::class)->handle($first, ['expires_at' => '2028-01-09']);
        app(RenewExpiringDocument::class)->handle($second, ['expires_at' => '2029-01-09']);

        expect($this->vehicle->documents()->count())->toBe(3)
            ->and($this->vehicle->documents()->current()->sole()->expires_at?->toDateString())->toBe('2029-01-09');
    });
});

describe('audit log', function (): void {
    it('records registrations and renewals without document numbers or notes', function (): void {
        $first = ($this->register)(['notes' => 'Observación privada']);
        $renewed = app(RenewExpiringDocument::class)->handle($first, ['number' => 'AT-999', 'expires_at' => '2028-01-09']);

        $entries = AuditEntry::query()
            ->where('subject_type', ExpiringDocument::class)
            ->orderBy('id')
            ->get();

        expect($entries->pluck('event')->all())->toBe(['created', 'updated', 'created', 'document_renewed']);

        $renewal = $entries->last();

        expect($renewal->subject_id)->toBe($renewed->getKey())
            ->and($renewal->actor?->is($this->admin))->toBeTrue()
            ->and($renewal->company_id)->toBe($this->company->getKey())
            ->and($renewal->properties['previous_id'])->toBe($first->getKey())
            ->and($renewal->properties['subject_label'])->toBe('SOAT · TST001');

        $logged = $entries->map(fn (AuditEntry $entry): string => json_encode($entry->properties))->implode(' ');

        expect($logged)->not->toContain('AT-1234567')
            ->not->toContain('AT-999')
            ->not->toContain('Observación privada');
    });
});
