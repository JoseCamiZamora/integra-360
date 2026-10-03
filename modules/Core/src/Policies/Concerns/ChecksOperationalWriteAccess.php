<?php

declare(strict_types=1);

namespace Modules\Core\Policies\Concerns;

use Modules\Core\Licensing\ModuleAccess;

/**
 * Write abilities on Core operational data (people, vehicles, documents,
 * assignments, company document types) require write access
 * (ModuleAccess::operationalLevel()): with the licenses expired, the
 * company keeps reading but cannot create or change anything. Going
 * through the policies hides the screens' write actions and rejects them
 * if a Livewire request calls them anyway.
 */
trait ChecksOperationalWriteAccess
{
    private function canWriteOperationalData(): bool
    {
        return app(ModuleAccess::class)->currentOperationalLevel()->canWrite();
    }
}
