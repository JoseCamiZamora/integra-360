<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Variant of BelongsToCompany for catalogs that mix global records (company_id
 * NULL, managed by the platform) with records of one company (configurable
 * document types, later the PESV checklists).
 *
 * - Queries see the global records plus the active company's
 *   (CompanyOrGlobalScope); without an active company they throw.
 * - Created inside a company context: the record belongs to that company.
 *   A global record is only created explicitly, with createGlobal() and
 *   outside any company context.
 * - Global records cannot be changed from inside a company context, and no
 *   record moves between companies.
 *
 * @mixin Model
 */
trait BelongsToCompanyOrGlobal
{
    private bool $creatingGlobal = false;

    public static function bootBelongsToCompanyOrGlobal(): void
    {
        static::addGlobalScope(new CompanyOrGlobalScope);

        static::creating(function (Model $model): void {
            /** @var Model&self $model */
            $active = CompanyContext::id();
            $current = $model->getAttribute('company_id');

            if ($model->creatingGlobal) {
                if ($active !== null || filled($current)) {
                    throw MissingCompanyContext::crossCompanyWrite($model::class);
                }

                return;
            }

            if (blank($current)) {
                $model->setAttribute('company_id', $active ?? throw MissingCompanyContext::forWrite($model::class));

                return;
            }

            if ($active !== null && $current !== $active) {
                throw MissingCompanyContext::crossCompanyWrite($model::class);
            }
        });

        $guard = function (Model $model): void {
            if ($model->isDirty('company_id')) {
                throw MissingCompanyContext::crossCompanyWrite($model::class);
            }

            if ($model->getOriginal('company_id') === null && CompanyContext::check()) {
                throw MissingCompanyContext::crossCompanyWrite($model::class);
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }

    /**
     * Creates a global record. Only outside a company context (platform
     * panel, seeders); authorization is the caller's job (policies).
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createGlobal(array $attributes): self
    {
        $model = new self;
        $model->fill($attributes);
        $model->creatingGlobal = true;
        $model->save();
        $model->creatingGlobal = false;

        return $model;
    }

    public function isGlobal(): bool
    {
        return $this->getAttribute('company_id') === null;
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(CompanyContext::companyModel(), 'company_id');
    }

    /**
     * Only the global records, without an active company (platform panel,
     * seeders). Not a cross-company query: global records belong to nobody.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeGlobalOnly(Builder $query): Builder
    {
        return $query->withoutGlobalScope(CompanyOrGlobalScope::class)
            ->whereNull($this->qualifyColumn('company_id'));
    }
}
