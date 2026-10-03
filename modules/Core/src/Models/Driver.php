<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\DriverFactory;
use Modules\Core\Enums\LicenseCategory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Driver profile of a person (1:1). The license validity is not stored
 * here: it is an ExpiringDocument of type "person.driving_license", so
 * alerts and compliance follow a single mechanism.
 *
 * Soft deleted when the person stops driving (assignments keep their
 * history) and restored if they drive again.
 *
 * @property string $id
 * @property string $company_id
 * @property string $person_id
 * @property string $license_number
 * @property LicenseCategory $license_category
 * @property int|null $experience_years
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Person $person
 */
#[Fillable(['license_number', 'license_category', 'experience_years'])]
class Driver extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<DriverFactory> */
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * The license number stays out of the audit log (personal data).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['person_id', 'license_category', 'experience_years'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function casts(): array
    {
        return [
            'license_category' => LicenseCategory::class,
            'experience_years' => 'integer',
        ];
    }

    protected static function newFactory(): DriverFactory
    {
        return DriverFactory::new();
    }
}
