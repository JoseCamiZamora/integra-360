<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Contracts\Documentable;
use Modules\Core\Events\ExpiringDocumentRegistered;
use Modules\Core\Models\DocumentType;
use Modules\Core\Models\ExpiringDocument;
use Modules\Core\Support\DocumentFileStore;
use Modules\Core\Support\ExpiringDocumentInput;

/**
 * Registers the first document of a type for a person or a vehicle. If the
 * entity already has a current document of that type, it must be renewed
 * instead (RenewExpiringDocument): history is never overwritten.
 */
final class RegisterExpiringDocument
{
    public function __construct(
        private readonly DocumentFileStore $files,
    ) {}

    /**
     * @param  array<string, mixed>  $data  number, issuer, issued_at, expires_at, notes
     * @param  list<UploadedFile>  $files
     *
     * @throws ValidationException
     */
    public function handle(Documentable&Model $documentable, DocumentType $type, array $data, array $files = []): ExpiringDocument
    {
        if (! $type->is_active || ! $type->appliesTo($documentable)) {
            throw ValidationException::withMessages([
                'document_type_id' => __('core::documents.errors.type_not_applicable'),
            ]);
        }

        $validated = ExpiringDocumentInput::validate($type, $data);
        $this->files->validate($files);

        $document = DB::transaction(function () use ($documentable, $type, $validated, $files): ExpiringDocument {
            $hasCurrent = $documentable->documents()
                ->where('document_type_id', $type->getKey())
                ->current()
                ->lockForUpdate()
                ->exists();

            if ($hasCurrent) {
                throw ValidationException::withMessages([
                    'document_type_id' => __('core::documents.errors.already_current'),
                ]);
            }

            /** @var ExpiringDocument $document */
            $document = $documentable->documents()->create([
                'document_type_id' => $type->getKey(),
                ...$validated,
            ]);

            $this->files->store($document, $files);

            return $document;
        });

        ExpiringDocumentRegistered::dispatch($document, $documentable);

        return $document;
    }
}
