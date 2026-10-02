<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use LogicException;

/**
 * Thrown when company data is read or written without an active company.
 * Failing loudly is the point: returning every company's rows is never safe.
 */
final class MissingCompanyContext extends LogicException
{
    public static function forQuery(string $model): self
    {
        return new self("No active company: [{$model}] cannot be queried. Wrap the code in CompanyContext::run().");
    }

    public static function forWrite(string $model): self
    {
        return new self("No active company: [{$model}] cannot be saved without a company_id. Wrap the code in CompanyContext::run().");
    }

    public static function crossCompanyWrite(string $model): self
    {
        return new self("[{$model}] belongs to another company than the active one.");
    }
}
