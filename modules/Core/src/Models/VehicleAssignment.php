<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A vehicle assigned to a driver. ends_at NULL = current. A vehicle has at
 * most one current driver and a driver at most one current vehicle
 * (unique indexes); assigning closes the previous ones (AssignVehicle).
 * Never deleted: it is the history.
 *
 * @property string $id
 * @property string $company_id
 * @property string $vehicle_id
 * @property string $driver_id
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Vehicle $vehicle
 * @property-read Driver $driver
 */
#[Fillable(['vehicle_id', 'driver_id', 'starts_at', 'ends_at', 'notes'])]
class VehicleAssignment extends Model
{
    use BelongsToCompany, HasUlids, LogsActivity;

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class)->withTrashed();
    }

    public function isCurrent(): bool
    {
        return $this->ends_at === null;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('ends_at');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['vehicle_id', 'driver_id', 'starts_at', 'ends_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
