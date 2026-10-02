<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\ModuleLicenseFactory;
use Modules\Core\Enums\ModuleAccessLevel;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A company's license for one module. Payment is handled manually.
 *
 * @property string $id
 * @property string $company_id
 * @property string $module_code
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at last valid day; null = no expiry
 * @property int|null $max_vehicles null = unlimited
 * @property int|null $max_people null = unlimited
 * @property string|null $notes
 * @property bool $is_active
 */
#[Fillable(['module_code', 'starts_at', 'ends_at', 'max_vehicles', 'max_people', 'notes', 'is_active'])]
class ModuleLicense extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<ModuleLicenseFactory> */
    use HasFactory, HasUlids, LogsActivity;

    /**
     * Days of read-only access after a license expires.
     */
    public const int READ_ONLY_GRACE_DAYS = 30;

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_code', 'code');
    }

    public function accessLevel(?Carbon $today = null): ModuleAccessLevel
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();

        if (! $this->is_active || $this->starts_at->greaterThan($today)) {
            return ModuleAccessLevel::None;
        }

        if ($this->ends_at === null || $this->ends_at->greaterThanOrEqualTo($today)) {
            return ModuleAccessLevel::Full;
        }

        if ($this->ends_at->copy()->addDays(self::READ_ONLY_GRACE_DAYS)->greaterThanOrEqualTo($today)) {
            return ModuleAccessLevel::ReadOnly;
        }

        return ModuleAccessLevel::None;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'max_vehicles' => 'integer',
            'max_people' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): ModuleLicenseFactory
    {
        return ModuleLicenseFactory::new();
    }
}
