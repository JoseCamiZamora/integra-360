<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Core\Models\Vehicle;

/**
 * The normalised plate is not used by another (not deleted) vehicle of the
 * active company. The company scope does the filtering, as everywhere; the
 * unique index on live_plate is the final guarantee.
 */
final class UniquePlate implements ValidationRule
{
    public function __construct(
        private readonly ?string $ignoreVehicleId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $taken = Vehicle::query()
            ->where('plate', Vehicle::normalizePlate($value))
            ->when($this->ignoreVehicleId, fn ($query, string $id) => $query->whereKeyNot($id))
            ->exists();

        if ($taken) {
            $fail('core::vehicles.errors.plate_taken')->translate();
        }
    }
}
