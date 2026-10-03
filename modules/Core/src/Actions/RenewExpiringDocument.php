<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Contracts\Documentable;
use Modules\Core\Enums\AuditEvent;
use Modules\Core\Events\ExpiringDocumentRenewed;
use Modules\Core\Models\ExpiringDocument;
use Modules\Core\Support\DocumentFileStore;
use Modules\Core\Support\ExpiringDocumentInput;

/**
 * Renews a document without overwriting it: a new record becomes the
 * current one and the previous one is kept with is_current = false, with
 * its files. Both changes and the renewal itself go to the audit log.
 */
final class RenewExpiringDocument
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
    public function handle(ExpiringDocument $current, array $data, array $files = []): ExpiringDocument
    {
        $type = $current->documentType;
        $validated = ExpiringDocumentInput::validate($type, $data);
        $this->files->validate($files);

        $renewed = DB::transaction(function () use ($current, $type, $validated, $files): ExpiringDocument {
            $previous = ExpiringDocument::query()->lockForUpdate()->find($current->getKey());

            if (! $previous instanceof ExpiringDocument || ! $previous->is_current) {
                throw ValidationException::withMessages([
                    'document' => __('core::documents.errors.not_current'),
                ]);
            }

            $previous->is_current = false;
            $previous->save();

            $renewed = new ExpiringDocument([
                'document_type_id' => $type->getKey(),
                ...$validated,
            ]);
            $renewed->documentable()->associate($this->documentable($previous));
            $renewed->save();

            $this->files->store($renewed, $files);

            activity()
                ->performedOn($renewed)
                ->event(AuditEvent::DocumentRenewed->value)
                ->withProperties(['previous_id' => $previous->getKey(), 'expires_at' => $validated['expires_at']])
                ->log(AuditEvent::DocumentRenewed->value);

            return $renewed;
        });

        ExpiringDocumentRenewed::dispatch($renewed, $current->refresh(), $this->documentable($renewed));

        return $renewed;
    }

    private function documentable(ExpiringDocument $document): Documentable&Model
    {
        $documentable = $document->documentable;

        if (! $documentable instanceof Documentable) {
            throw new \LogicException('The document has no documentable entity.');
        }

        return $documentable;
    }
}
