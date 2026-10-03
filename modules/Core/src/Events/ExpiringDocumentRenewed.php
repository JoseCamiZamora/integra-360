<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\Core\Contracts\Documentable;
use Modules\Core\Models\ExpiringDocument;

/**
 * Public event: a document was renewed. $document is the new current one,
 * $previous the one it replaced (kept, no longer current). Consumed by the
 * alerts (I360-03).
 */
final class ExpiringDocumentRenewed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public readonly string $companyId;

    public function __construct(
        public readonly ExpiringDocument $document,
        public readonly ExpiringDocument $previous,
        public readonly Documentable&Model $documentable,
    ) {
        $this->companyId = $document->company_id;
    }
}
