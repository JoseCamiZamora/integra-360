<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Support\Tenancy\CompanyContext;
use App\Support\Tenancy\CompanyScope;
use App\Support\Tenancy\CompanyScopeBypassed;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

/**
 * Audit log entry (spatie/laravel-activitylog). Read-only from the panels.
 *
 * Company-scoped for reading, like any company data. Unlike BelongsToCompany
 * models, company_id may be NULL: platform-level events (failed sign-ins with
 * an unknown identifier, platform administrator actions...).
 *
 * @property string|null $company_id
 * @property string|null $ip_address
 */
class AuditEntry extends Activity
{
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Who did it. Causers are always users: a plain BelongsTo avoids the
     * polymorphic eager load, which builds a company-scoped query first.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    /**
     * The platform administrator's cross-company view. Recorded.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutCompanyScope(Builder $query): Builder
    {
        Gate::authorize(CompanyContext::BYPASS_ABILITY);

        CompanyScopeBypassed::dispatch(static::class);

        return $query->withoutGlobalScope(CompanyScope::class);
    }
}
