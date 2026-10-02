<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Gate;

/**
 * For every model that holds a company's data (it needs a `company_id`
 * column). It filters all queries by the active company, fills `company_id`
 * on create and refuses to move a record to another company.
 *
 * Never filter by company_id by hand: use this trait.
 *
 * @mixin Model
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (Model $model): void {
            $active = CompanyContext::id();
            $current = $model->getAttribute('company_id');

            if (blank($current)) {
                $model->setAttribute('company_id', $active ?? throw MissingCompanyContext::forWrite($model::class));

                return;
            }

            if ($active !== null && $current !== $active) {
                throw MissingCompanyContext::crossCompanyWrite($model::class);
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('company_id')) {
                throw MissingCompanyContext::crossCompanyWrite($model::class);
            }
        });
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(CompanyContext::companyModel(), 'company_id');
    }

    /**
     * Explicit cross-company query, only for the platform administrator
     * (the `bypass-company-scope` ability). Always recorded in the audit log.
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
