<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Modules\Core\Contracts\ComplianceReport;
use Modules\Core\Contracts\Documentable;
use Modules\Core\Contracts\DocumentCompliance;
use Modules\Core\Enums\DocumentStatus;
use Modules\Core\Models\DocumentType;
use Modules\Core\Models\ExpiringDocument;
use Modules\Core\Models\Vehicle;

/**
 * DocumentCompliance from the database: two queries for any number of
 * entities of the same kind (the applicable types and their current
 * documents), then everything is evaluated in memory.
 */
final class DatabaseDocumentCompliance implements DocumentCompliance
{
    public function forVehicle(Vehicle $vehicle, ?CarbonInterface $at = null): ComplianceReport
    {
        return $this->forEntities(collect([$vehicle]), $at)[$vehicle->getKey()];
    }

    public function forVehicles(Collection $vehicles, ?CarbonInterface $at = null): array
    {
        return $this->forEntities($vehicles, $at);
    }

    /**
     * @param  Collection<int, covariant Documentable&Model>  $entities  all of the same kind
     * @return array<string, ComplianceReport>
     */
    private function forEntities(Collection $entities, ?CarbonInterface $at): array
    {
        $first = $entities->first();

        if ($first === null) {
            return [];
        }

        $types = DocumentType::query()
            ->where('is_active', true)
            ->where('applies_to', $first->documentSubject()->value)
            ->get()
            ->keyBy('id');

        $documents = ExpiringDocument::query()
            ->current()
            ->where('documentable_type', $first->getMorphClass())
            ->whereIn('documentable_id', $entities->map(fn (Model $entity): mixed => $entity->getKey())->all())
            ->whereIn('document_type_id', $types->keys()->all())
            ->get()
            ->groupBy('documentable_id');

        $reports = [];

        foreach ($entities as $entity) {
            $reports[(string) $entity->getKey()] = $this->evaluate(
                $entity,
                $types->filter(fn (DocumentType $type): bool => $type->appliesTo($entity)),
                $documents->get($entity->getKey(), collect()),
                $at,
            );
        }

        return $reports;
    }

    /**
     * @param  Collection<string, DocumentType>  $types  applicable types, keyed by id
     * @param  Collection<int, ExpiringDocument>  $documents  current documents of the entity
     */
    private function evaluate(Documentable $entity, Collection $types, Collection $documents, ?CarbonInterface $at): ComplianceReport
    {
        $expired = [];
        $expiringSoon = [];
        $present = [];

        foreach ($documents as $document) {
            $type = $types->get($document->document_type_id);

            if (! $type instanceof DocumentType) {
                continue;
            }

            $document->setRelation('documentType', $type);
            $present[$type->getKey()] = true;

            match ($document->status($at)) {
                DocumentStatus::Expired => $expired[] = $document,
                DocumentStatus::ExpiringSoon => $expiringSoon[] = $document,
                default => null,
            };
        }

        $missing = $entity->demandsRequiredDocuments()
            ? $types->filter(fn (DocumentType $type): bool => $type->is_required && ! isset($present[$type->getKey()]))->values()->all()
            : [];

        return ComplianceReport::evaluate($expired, $expiringSoon, $missing);
    }
}
