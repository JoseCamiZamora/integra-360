<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Enums\LicenseCategory;
use Modules\Core\Enums\VehicleType;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Editable equivalence: a license category allowed for a vehicle type.
 * Platform-wide data (no company), seeded with initial values to be
 * validated with the pilot company. Not a legal rule: assigning a driver
 * whose category is not listed only warns.
 *
 * @property int $id
 * @property VehicleType $vehicle_type
 * @property LicenseCategory $license_category
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['vehicle_type', 'license_category'])]
class VehicleTypeLicenseCategory extends Model
{
    use LogsActivity;

    public static function allows(VehicleType $vehicleType, LicenseCategory $category): bool
    {
        return self::query()
            ->where('vehicle_type', $vehicleType->value)
            ->where('license_category', $category->value)
            ->exists();
    }

    /**
     * @return list<LicenseCategory>
     */
    public static function allowedFor(VehicleType $vehicleType): array
    {
        return self::query()
            ->where('vehicle_type', $vehicleType->value)
            ->orderBy('license_category')
            ->pluck('license_category')
            ->map(fn (mixed $category): LicenseCategory => $category instanceof LicenseCategory ? $category : LicenseCategory::from((string) $category))
            ->values()
            ->all();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['vehicle_type', 'license_category'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'vehicle_type' => VehicleType::class,
            'license_category' => LicenseCategory::class,
        ];
    }
}
