<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Modules\ModuleServiceProvider;
use App\Support\Tenancy\CompanyContext;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\UserFactory;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Enums\DocumentType;
use Modules\Core\Notifications\ResetPasswordNotification;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * A person who signs in. Users are not company-scoped (one account may serve
 * several companies, decision 2): the users of the active company are the
 * ones with a membership in it (see scopeInActiveCompany()).
 *
 * Roles are per company (spatie teams = CompanyContext).
 *
 * @property int $id
 * @property string $name
 * @property DocumentType $document_type
 * @property string $document_number
 * @property string|null $email
 * @property string|null $phone
 * @property string $password
 * @property bool $must_change_password
 * @property bool $is_platform_admin
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Membership|null $membership
 */
#[Fillable(['name', 'document_type', 'document_number', 'email', 'phone', 'password', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    public const string PLATFORM_PANEL_ID = 'platform';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'must_change_password' => false,
        'is_platform_admin' => false,
    ];

    public function isPlatformAdmin(): bool
    {
        return $this->is_platform_admin;
    }

    /**
     * Every company of the user, active or not. Readable without a company
     * context (needed to sign in).
     *
     * @return BelongsToMany<Company, $this, Membership, 'membership'>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->using(Membership::class)
            ->as('membership')
            ->withPivot(['id', 'branch_id', 'is_active', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Company-scoped: the membership in the active company only.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Active companies where this user has an active membership.
     *
     * @return Collection<int, Company>
     */
    public function activeCompanies(): Collection
    {
        return $this->companies()
            ->wherePivot('is_active', true)
            ->where('companies.is_active', true)
            ->orderBy('legal_name')
            ->get();
    }

    public function isActiveMemberOf(Company $company): bool
    {
        return $this->activeCompanies()->contains($company);
    }

    /**
     * Users that are members (active or not) of the active company.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeInActiveCompany(Builder $query): Builder
    {
        return $query->whereHas('memberships');
    }

    /**
     * Permission check inside another company's context (e.g. while choosing
     * the company to enter). Inside a request, use $user->can() as usual.
     */
    public function hasPermissionInCompany(Company $company, string $permission): bool
    {
        return CompanyContext::run($company, function () use ($permission): bool {
            $this->forgetCompanyRoles();

            try {
                return $this->checkPermissionTo($permission);
            } finally {
                $this->forgetCompanyRoles();
            }
        });
    }

    /**
     * @return list<string>
     */
    public function roleNamesInCompany(Company $company): array
    {
        return CompanyContext::run($company, function (): array {
            $this->forgetCompanyRoles();

            try {
                return array_values($this->getRoleNames()->all());
            } finally {
                $this->forgetCompanyRoles();
            }
        });
    }

    /**
     * Roles are loaded for the company active when first read: forget them
     * after switching companies in the same process.
     */
    public function forgetCompanyRoles(): static
    {
        return $this->unsetRelation('roles')->unsetRelation('permissions');
    }

    /**
     * Drivers only (no other role anywhere) get the 30-day "remember me".
     */
    public function isOnlyDriver(): bool
    {
        $roles = collect($this->activeCompanies())
            ->flatMap(fn (Company $company): array => $this->roleNamesInCompany($company))
            ->unique();

        return $roles->isNotEmpty() && $roles->every(fn (string $role): bool => $role === CompanyRole::Driver->value);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            self::PLATFORM_PANEL_ID => $this->isPlatformAdmin(),
            ModuleServiceProvider::ADMIN_PANEL_ID => $this->getTenants($panel)->isNotEmpty(),
            default => false,
        };
    }

    /**
     * Companies whose panel (/app) this user may open.
     *
     * @return Collection<int, Company>
     */
    public function getTenants(Panel $panel): Collection
    {
        return $this->activeCompanies()
            ->filter(fn (Company $company): bool => $this->hasPermissionInCompany($company, 'core.panel.access'))
            ->values();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Company
            && $this->isActiveMemberOf($tenant)
            && $this->hasPermissionInCompany($tenant, 'core.panel.access');
    }

    public function getDefaultTenant(Panel $panel): ?Model
    {
        $tenants = $this->getTenants($panel);

        return $tenants->firstWhere('id', session('company_id')) ?? $tenants->first();
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Never the password or the remember token.
        return LogOptions::defaults()
            ->logOnly(['name', 'document_type', 'document_number', 'email', 'phone', 'must_change_password', 'is_platform_admin'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'is_platform_admin' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
