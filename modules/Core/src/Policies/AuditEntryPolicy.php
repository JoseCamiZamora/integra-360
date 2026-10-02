<?php

declare(strict_types=1);

namespace Modules\Core\Policies;

use App\Support\Tenancy\CompanyContext;
use Modules\Core\Models\AuditEntry;
use Modules\Core\Models\User;

/**
 * Read-only audit log: the company administrator sees their company's,
 * the platform administrator everything (an explicit, recorded query).
 */
final class AuditEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin() || $user->checkPermissionTo('core.audit.view');
    }

    public function view(User $user, AuditEntry $entry): bool
    {
        return $user->isPlatformAdmin()
            || ($entry->company_id !== null
                && $entry->company_id === CompanyContext::id()
                && $user->checkPermissionTo('core.audit.view'));
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditEntry $entry): bool
    {
        return false;
    }

    public function delete(User $user, AuditEntry $entry): bool
    {
        return false;
    }
}
