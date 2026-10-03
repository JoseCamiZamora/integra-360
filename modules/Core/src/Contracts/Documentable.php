<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Core\Enums\DocumentAppliesTo;
use Modules\Core\Enums\VehicleType;
use Modules\Core\Models\ExpiringDocument;

/**
 * An entity that holds expiring documents (people and vehicles). Its morph
 * alias is documentSubject()->value.
 */
interface Documentable
{
    public function documentSubject(): DocumentAppliesTo;

    /**
     * For vehicles: their type, to match document types limited to some
     * vehicle types. Null for any other entity.
     */
    public function documentVehicleType(): ?VehicleType;

    /**
     * Whether document types marked as required are demanded from this
     * entity: always for vehicles; for people, only drivers.
     */
    public function demandsRequiredDocuments(): bool;

    /**
     * Short label for screens and the audit log (plate, full name).
     */
    public function documentLabel(): string;

    /**
     * @return MorphMany<ExpiringDocument, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function documents(): MorphMany;
}
