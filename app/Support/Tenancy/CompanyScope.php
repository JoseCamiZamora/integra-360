<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filters by the active company and fails safe (throws) when there is none.
 *
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 */
final class CompanyScope implements Scope
{
    /**
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = CompanyContext::id() ?? throw MissingCompanyContext::forQuery($model::class);

        $builder->where($model->qualifyColumn('company_id'), $companyId);
    }
}
