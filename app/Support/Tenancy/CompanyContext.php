<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * The active company: every model using BelongsToCompany is filtered by it.
 *
 * - Web requests: a middleware sets it for the rest of the request with
 *   activate() (Livewire re-runs middleware before, not around, a component,
 *   so a closure cannot cover it). It is cleared when the request ends.
 * - Everything else (jobs, commands, seeders, the platform panel, actions
 *   that receive a company): run($company, fn () => ...), which restores the
 *   previous context afterwards.
 *
 * Without an active company, scoped queries throw MissingCompanyContext.
 */
final class CompanyContext
{
    /**
     * Gate ability for explicit cross-company queries (withoutCompanyScope()).
     */
    public const string BYPASS_ABILITY = 'bypass-company-scope';

    private static ?string $companyId = null;

    /**
     * @var class-string<Model>|null
     */
    private static ?string $companyModel = null;

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function run(HasCompanyId|string $company, Closure $callback): mixed
    {
        $previous = self::$companyId;
        self::$companyId = self::normalize($company);

        try {
            return $callback();
        } finally {
            self::$companyId = $previous;
        }
    }

    /**
     * Sets the company for the rest of the current HTTP request. Only for
     * middleware; anything else uses run().
     */
    public static function activate(HasCompanyId|string $company): void
    {
        self::$companyId = self::normalize($company);
    }

    public static function forget(): void
    {
        self::$companyId = null;
    }

    public static function id(): ?string
    {
        return self::$companyId;
    }

    public static function check(): bool
    {
        return self::$companyId !== null;
    }

    /**
     * @param  string  $model  what was being accessed, for the error message
     */
    public static function requireId(string $model = 'company data'): string
    {
        return self::$companyId ?? throw MissingCompanyContext::forQuery($model);
    }

    /**
     * Registered by Core so Kernel code can build the `company` relationship
     * without importing the Company class.
     *
     * @param  class-string<Model>  $model
     */
    public static function useCompanyModel(string $model): void
    {
        self::$companyModel = $model;
    }

    /**
     * @return class-string<Model>
     */
    public static function companyModel(): string
    {
        return self::$companyModel ?? throw new MissingCompanyContext('The company model has not been registered.');
    }

    private static function normalize(HasCompanyId|string $company): string
    {
        return $company instanceof HasCompanyId ? $company->companyId() : $company;
    }
}
