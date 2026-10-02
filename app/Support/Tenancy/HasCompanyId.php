<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

/**
 * Anything that identifies a company (the Core Company model implements it),
 * so the Kernel can receive companies without depending on Core.
 */
interface HasCompanyId
{
    public function companyId(): string;
}
