<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Core\Enums\VehicleType;
use Modules\Core\Models\Vehicle;

/**
 * Colombian plate format for the vehicle type (config integra.plates),
 * checked on the normalised plate: "tst-001" is valid as TST001.
 */
final class ValidPlate implements ValidationRule
{
    public function __construct(
        private readonly ?VehicleType $vehicleType,
    ) {}

    public static function formatFor(?VehicleType $type): string
    {
        /** @var array<string, string> $byType */
        $byType = config('integra.plates.by_vehicle_type', []);
        $format = $byType[$type?->value] ?? (string) config('integra.plates.default');

        return (string) config('integra.plates.formats.'.$format);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::formatFor($this->vehicleType), Vehicle::normalizePlate($value)) !== 1) {
            $fail('core::vehicles.errors.plate_format')->translate();
        }
    }
}
