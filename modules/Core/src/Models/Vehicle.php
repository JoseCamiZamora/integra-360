<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Contracts\Documentable;
use Modules\Core\Database\Factories\VehicleFactory;
use Modules\Core\Enums\DocumentAppliesTo;
use Modules\Core\Enums\VehicleOwnership;
use Modules\Core\Enums\VehicleServiceType;
use Modules\Core\Enums\VehicleStatus;
use Modules\Core\Enums\VehicleType;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A company vehicle. Never deleted physically (soft deletes); a retired
 * vehicle is kept for its history.
 *
 * @property string $id
 * @property string $company_id
 * @property string|null $branch_id
 * @property string $plate
 * @property VehicleType $vehicle_type
 * @property string $brand
 * @property string $model_line
 * @property int $model_year
 * @property string|null $color
 * @property string|null $vin
 * @property int|null $load_capacity_kg
 * @property VehicleOwnership $ownership
 * @property VehicleServiceType $service_type
 * @property int|null $odometer_km
 * @property VehicleStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'branch_id', 'plate', 'vehicle_type', 'brand', 'model_line', 'model_year', 'color', 'vin',
    'load_capacity_kg', 'ownership', 'service_type', 'odometer_km', 'status',
])]
class Vehicle extends Model implements Documentable
{
    use BelongsToCompany;

    /** @use HasFactory<VehicleFactory> */
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Uppercase, without spaces, hyphens or dots ("tst-001" → "TST001").
     */
    public static function normalizePlate(string $plate): string
    {
        return strtoupper((string) preg_replace('/[\s\-.]+/', '', $plate));
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<VehicleAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(VehicleAssignment::class);
    }

    /**
     * @return HasOne<VehicleAssignment, $this>
     */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(VehicleAssignment::class)->whereNull('ends_at');
    }

    public function isRetired(): bool
    {
        return $this->status === VehicleStatus::Retired;
    }

    /**
     * Vehicles that count for the license limits: not retired (deleted ones
     * are already excluded by the soft delete scope).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeInService(Builder $query): Builder
    {
        return $query->where('status', '!=', VehicleStatus::Retired->value);
    }

    /**
     * @return MorphMany<ExpiringDocument, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(ExpiringDocument::class, 'documentable');
    }

    public function documentSubject(): DocumentAppliesTo
    {
        return DocumentAppliesTo::Vehicle;
    }

    public function documentVehicleType(): VehicleType
    {
        return $this->vehicle_type;
    }

    public function demandsRequiredDocuments(): bool
    {
        return true;
    }

    public function documentLabel(): string
    {
        return $this->plate;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['plate', 'vehicle_type', 'brand', 'model_line', 'model_year', 'ownership', 'service_type', 'status', 'branch_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return Attribute<string, string>
     */
    protected function plate(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => self::normalizePlate($value));
    }

    protected function casts(): array
    {
        return [
            'vehicle_type' => VehicleType::class,
            'ownership' => VehicleOwnership::class,
            'service_type' => VehicleServiceType::class,
            'status' => VehicleStatus::class,
            'model_year' => 'integer',
            'load_capacity_kg' => 'integer',
            'odometer_km' => 'integer',
        ];
    }

    protected static function newFactory(): VehicleFactory
    {
        return VehicleFactory::new();
    }
}
