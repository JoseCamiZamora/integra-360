<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\Core\Contracts\Documentable;
use Modules\Core\Models\ExpiringDocument;

/**
 * Public event: a first document of a type was registered for a person or a
 * vehicle. Consumed by the alerts (I360-03).
 *
 * Carries the company id so a queued listener can open the company context
 * (CompanyContext::run()) before touching company data.
 */
final class ExpiringDocumentRegistered implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public readonly string $companyId;

    public function __construct(
        public readonly ExpiringDocument $document,
        public readonly Documentable&Model $documentable,
    ) {
        $this->companyId = $document->company_id;
    }
}
