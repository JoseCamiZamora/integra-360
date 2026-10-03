<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Modules\Core\Enums\ComplianceStatus;
use Modules\Core\Models\DocumentType;
use Modules\Core\Models\ExpiringDocument;

/**
 * Document status of one person or vehicle at a given moment (value
 * object, built by DocumentCompliance). Only current documents of active
 * types that apply to the entity are considered.
 */
final readonly class ComplianceReport
{
    /**
     * @param  list<ExpiringDocument>  $expired  current documents already expired
     * @param  list<ExpiringDocument>  $expiringSoon  current documents within their warning days
     * @param  list<DocumentType>  $missing  required types without a current document
     * @param  bool  $blocksOperation  a type that blocks operation is missing or expired
     */
    public function __construct(
        public ComplianceStatus $status,
        public array $expired,
        public array $expiringSoon,
        public array $missing,
        public bool $blocksOperation,
    ) {}

    /**
     * Overall status: expired or missing → non compliant; otherwise anything
     * expiring soon → expiring soon; otherwise compliant.
     *
     * @param  list<ExpiringDocument>  $expired  with their documentType loaded
     * @param  list<ExpiringDocument>  $expiringSoon
     * @param  list<DocumentType>  $missing
     */
    public static function evaluate(array $expired, array $expiringSoon, array $missing): self
    {
        $status = match (true) {
            $expired !== [] || $missing !== [] => ComplianceStatus::NonCompliant,
            $expiringSoon !== [] => ComplianceStatus::ExpiringSoon,
            default => ComplianceStatus::Compliant,
        };

        $blocks = array_any($missing, fn (DocumentType $type): bool => $type->blocks_operation)
            || array_any($expired, fn (ExpiringDocument $document): bool => $document->documentType->blocks_operation);

        return new self($status, $expired, $expiringSoon, $missing, $blocks);
    }

    public function isCompliant(): bool
    {
        return $this->status === ComplianceStatus::Compliant;
    }
}
