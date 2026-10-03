<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Support\Tenancy\BelongsToCompanyOrGlobal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Contracts\Documentable;
use Modules\Core\Database\Factories\DocumentTypeFactory;
use Modules\Core\Enums\DocumentAppliesTo;
use Modules\Core\Enums\VehicleType;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Configurable catalog of expiring documents (SOAT, license...). Data, not
 * code: modules add their own types without changing Core.
 *
 * company_id NULL = global type (managed by the platform administrator);
 * otherwise a type of one company (managed by its administrator). Queries
 * see the global types plus the active company's (BelongsToCompanyOrGlobal).
 *
 * @property string $id
 * @property string|null $company_id
 * @property string $code
 * @property string $name
 * @property DocumentAppliesTo $applies_to
 * @property bool $requires_expiry
 * @property bool $is_required
 * @property list<string>|null $vehicle_types
 * @property bool $blocks_operation
 * @property int $warning_days
 * @property bool $is_sensitive
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'code', 'name', 'applies_to', 'requires_expiry', 'is_required', 'vehicle_types',
    'blocks_operation', 'warning_days', 'is_sensitive', 'is_active',
])]
class DocumentType extends Model
{
    use BelongsToCompanyOrGlobal;

    /** @use HasFactory<DocumentTypeFactory> */
    use HasFactory, HasUlids, LogsActivity;

    public const int DEFAULT_WARNING_DAYS = 30;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'requires_expiry' => true,
        'is_required' => false,
        'blocks_operation' => false,
        'warning_days' => self::DEFAULT_WARNING_DAYS,
        'is_sensitive' => false,
        'is_active' => true,
    ];

    /**
     * @return HasMany<ExpiringDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ExpiringDocument::class);
    }

    /**
     * The type can hold documents of this entity: same subject and, for
     * vehicles, a vehicle type it covers (no list = every type).
     */
    public function appliesTo(Documentable $documentable): bool
    {
        if ($this->applies_to !== $documentable->documentSubject()) {
            return false;
        }

        $vehicleType = $documentable->documentVehicleType();

        return $vehicleType === null
            || $this->vehicle_types === null
            || $this->vehicle_types === []
            || in_array($vehicleType->value, $this->vehicle_types, true);
    }

    /**
     * Active types that apply to the entity.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApplicableTo(Builder $query, Documentable $documentable): Builder
    {
        $query->where('is_active', true)->where('applies_to', $documentable->documentSubject()->value);

        $vehicleType = $documentable->documentVehicleType();

        if ($vehicleType instanceof VehicleType) {
            $query->where(fn (Builder $types) => $types
                ->whereNull('vehicle_types')
                ->orWhereJsonLength('vehicle_types', 0)
                ->orWhereJsonContains('vehicle_types', $vehicleType->value));
        }

        return $query;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'code', 'name', 'applies_to', 'requires_expiry', 'is_required', 'vehicle_types',
                'blocks_operation', 'warning_days', 'is_sensitive', 'is_active',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'applies_to' => DocumentAppliesTo::class,
            'requires_expiry' => 'boolean',
            'is_required' => 'boolean',
            'vehicle_types' => 'array',
            'blocks_operation' => 'boolean',
            'warning_days' => 'integer',
            'is_sensitive' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): DocumentTypeFactory
    {
        return DocumentTypeFactory::new();
    }
}
