<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Support\Tenancy\HasCompanyId;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\CompanyFactory;
use Modules\Core\Enums\CompanyMissionType;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A client company (the tenant). Its own row is not company-scoped: it is
 * the scope. Branches, memberships, licenses... carry its company_id.
 *
 * @property string $id
 * @property string $legal_name
 * @property string|null $trade_name
 * @property string $nit
 * @property CompanyMissionType $mission_type
 * @property string $city
 * @property string $department
 * @property string $address
 * @property string $phone
 * @property string $email
 * @property string $legal_representative
 * @property string|null $logo_path
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'legal_name', 'trade_name', 'nit', 'mission_type', 'city', 'department', 'address',
    'phone', 'email', 'legal_representative', 'logo_path', 'is_active',
])]
class Company extends Model implements HasCompanyId, HasName
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    public function companyId(): string
    {
        return $this->getKey();
    }

    public function displayName(): string
    {
        return $this->trade_name ?: $this->legal_name;
    }

    public function getFilamentName(): string
    {
        return $this->displayName();
    }

    /**
     * Company-scoped: only readable inside this company's context.
     *
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * Not scoped (the pivot is read through the join): used to know which
     * companies a user belongs to before any company is active.
     *
     * @return BelongsToMany<User, $this, Membership, 'membership'>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(Membership::class)
            ->as('membership')
            ->withPivot(['id', 'branch_id', 'is_active', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<ModuleLicense, $this>
     */
    public function licenses(): HasMany
    {
        return $this->hasMany(ModuleLicense::class);
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
            'mission_type' => CompanyMissionType::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): CompanyFactory
    {
        return CompanyFactory::new();
    }
}
