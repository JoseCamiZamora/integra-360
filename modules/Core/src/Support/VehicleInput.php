<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Core\Enums\VehicleOwnership;
use Modules\Core\Enums\VehicleServiceType;
use Modules\Core\Enums\VehicleStatus;
use Modules\Core\Enums\VehicleType;
use Modules\Core\Models\Branch;
use Modules\Core\Rules\ExistsInActiveCompany;
use Modules\Core\Rules\UniquePlate;
use Modules\Core\Rules\ValidPlate;

/**
 * Validation of a vehicle's data, shared by the actions and the screens.
 */
final class VehicleInput
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(?VehicleType $type, ?string $ignoreVehicleId = null): array
    {
        return [
            'branch_id' => ['nullable', new ExistsInActiveCompany(Branch::class)],
            'plate' => ['required', 'string', 'max:12', new ValidPlate($type), new UniquePlate($ignoreVehicleId)],
            'vehicle_type' => ['required', Rule::enum(VehicleType::class)],
            'brand' => ['required', 'string', 'max:60'],
            'model_line' => ['required', 'string', 'max:60'],
            'model_year' => ['required', 'integer', 'between:1950,'.((int) date('Y') + 1)],
            'color' => ['nullable', 'string', 'max:40'],
            'vin' => ['nullable', 'string', 'max:30'],
            'load_capacity_kg' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'ownership' => ['required', Rule::enum(VehicleOwnership::class)],
            'service_type' => ['required', Rule::enum(VehicleServiceType::class)],
            'odometer_km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'status' => ['sometimes', Rule::enum(VehicleStatus::class)],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(array $data, ?string $ignoreVehicleId = null): array
    {
        $type = self::type($data['vehicle_type'] ?? null);

        return Validator::make($data, self::rules($type, $ignoreVehicleId), [], self::attributes())->validate();
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        $attributes = [];

        foreach (array_keys(self::rules(null)) as $field) {
            $attributes[$field] = (string) __('core::vehicles.fields.'.$field);
        }

        return $attributes;
    }

    private static function type(mixed $value): ?VehicleType
    {
        return match (true) {
            $value instanceof VehicleType => $value,
            is_string($value) => VehicleType::tryFrom($value),
            default => null,
        };
    }
}
