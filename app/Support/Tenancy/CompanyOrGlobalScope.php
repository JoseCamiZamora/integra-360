<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global records (company_id NULL, shared by every company) plus the active
 * company's own records; never another company's. Fails safe (throws) when
 * there is no active company, like CompanyScope.
 *
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 */
final class CompanyOrGlobalScope implements Scope
{
    /**
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = CompanyContext::id() ?? throw MissingCompanyContext::forQuery($model::class);
        $column = $model->qualifyColumn('company_id');

        $builder->where(fn (Builder $query) => $query->whereNull($column)->orWhere($column, $companyId));
    }
}
