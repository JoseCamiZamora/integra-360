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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Contracts\Documentable;
use Modules\Core\Database\Factories\PersonFactory;
use Modules\Core\Enums\DocumentAppliesTo;
use Modules\Core\Enums\IdentityDocumentType;
use Modules\Core\Enums\PersonStatus;
use Modules\Core\Enums\VehicleType;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A worker of the company. May exist without a user account (user_id) and
 * may have a driver profile (driver). Never deleted physically.
 *
 * Personal data (Ley 1581): only what the operation needs. No health data,
 * photos or relatives.
 *
 * @property string $id
 * @property string $company_id
 * @property string|null $branch_id
 * @property int|null $user_id
 * @property IdentityDocumentType $document_type
 * @property string $document_number
 * @property string $first_name
 * @property string $last_name
 * @property Carbon|null $birth_date
 * @property string|null $phone
 * @property string|null $email
 * @property string $position
 * @property string|null $area
 * @property Carbon|null $hired_at
 * @property PersonStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $full_name
 * @property-read Driver|null $driver
 * @property-read User|null $user
 */
#[Fillable([
    'branch_id', 'document_type', 'document_number', 'first_name', 'last_name', 'birth_date',
    'phone', 'email', 'position', 'area', 'hired_at', 'status',
])]
class Person extends Model implements Documentable
{
    use BelongsToCompany;

    /** @use HasFactory<PersonFactory> */
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    protected $table = 'people';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * "1.020.304.050" → "1020304050" (same rule as user accounts).
     */
    public static function normalizeDocumentNumber(string $number): string
    {
        return strtoupper((string) preg_replace('/[\s.\-]+/', '', $number));
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasOne<Driver, $this>
     */
    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class);
    }

    /**
     * @return MorphMany<ExpiringDocument, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(ExpiringDocument::class, 'documentable');
    }

    public function isActive(): bool
    {
        return $this->status === PersonStatus::Active;
    }

    public function isDriver(): bool
    {
        return $this->driver !== null;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', PersonStatus::Active->value);
    }

    public function documentSubject(): DocumentAppliesTo
    {
        return DocumentAppliesTo::Person;
    }

    public function documentVehicleType(): ?VehicleType
    {
        return null;
    }

    /**
     * Required person documents (license, medical exam) are only demanded
     * from drivers.
     */
    public function demandsRequiredDocuments(): bool
    {
        return $this->isDriver();
    }

    public function documentLabel(): string
    {
        return $this->full_name;
    }

    /**
     * Names and work data only: no birth date, phone or e-mail in the log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['first_name', 'last_name', 'position', 'area', 'branch_id', 'status', 'user_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->first_name.' '.$this->last_name));
    }

    /**
     * @return Attribute<string, string>
     */
    protected function documentNumber(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => self::normalizeDocumentNumber($value));
    }

    protected function casts(): array
    {
        return [
            'document_type' => IdentityDocumentType::class,
            'birth_date' => 'date',
            'hired_at' => 'date',
            'status' => PersonStatus::class,
        ];
    }

    protected static function newFactory(): PersonFactory
    {
        return PersonFactory::new();
    }
}
