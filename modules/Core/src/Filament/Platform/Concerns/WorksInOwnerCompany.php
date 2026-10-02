<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Platform\Concerns;

use App\Support\Tenancy\CompanyContext;
use Modules\Core\Models\Company;

/**
 * For platform-panel relation managers that work on ONE company's data (its
 * licenses): that company becomes the active company for the rest of the
 * Livewire request, so the company scope applies instead of being bypassed.
 * Like the tenant middleware, it lasts until the request ends.
 */
trait WorksInOwnerCompany
{
    public function bootedWorksInOwnerCompany(): void
    {
        $owner = $this->getOwnerRecord();

        if ($owner instanceof Company) {
            CompanyContext::activate($owner);
        }
    }
}
