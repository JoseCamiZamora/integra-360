<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched every time a query explicitly leaves the company scope, so Core
 * can record it in the audit log.
 */
final class CompanyScopeBypassed
{
    use Dispatchable;

    /**
     * @param  class-string  $model
     */
    public function __construct(
        public readonly string $model,
    ) {}
}
