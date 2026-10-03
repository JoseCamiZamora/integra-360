<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Models\ExpiringDocument;
use Modules\Core\Support\ExpiringDocumentInput;

/**
 * Corrects the data of a document (a typo in the number or a date). A new
 * validity period is a renewal (RenewExpiringDocument), which keeps the
 * history. Changes go to the audit log (dates only, never the number).
 */
final class UpdateExpiringDocument
{
    /**
     * @param  array<string, mixed>  $data  number, issuer, issued_at, expires_at, notes
     *
     * @throws ValidationException
     */
    public function handle(ExpiringDocument $document, array $data): ExpiringDocument
    {
        $document->update(ExpiringDocumentInput::validate($document->documentType, $data));

        return $document;
    }
}
