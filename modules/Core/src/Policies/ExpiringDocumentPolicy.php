<?php

declare(strict_types=1);

namespace Modules\Core\Policies;

use App\Support\Tenancy\CompanyContext;
use Modules\Core\Models\ExpiringDocument;
use Modules\Core\Models\User;
use Modules\Core\Policies\Concerns\ChecksOperationalWriteAccess;

/**
 * Documents of the active company (their files: ExpiringDocumentFilePolicy).
 *
 * Explicit rule for the platform administrator: no access. Documents are a
 * company's operational data and the platform administrator has no role in
 * any company.
 */
final class ExpiringDocumentPolicy
{
    use ChecksOperationalWriteAccess;

    public function viewAny(User $user): bool
    {
        return ! $user->isPlatformAdmin() && $user->checkPermissionTo('core.documents.view');
    }

    public function view(User $user, ExpiringDocument $document): bool
    {
        return ! $user->isPlatformAdmin()
            && $this->owns($document)
            && $user->checkPermissionTo('core.documents.view');
    }

    public function create(User $user): bool
    {
        return ! $user->isPlatformAdmin() && $user->checkPermissionTo('core.documents.create')
            && $this->canWriteOperationalData();
    }

    /**
     * Corrections of the data (typos). Dates of a new validity period are a
     * renewal, which keeps the history.
     */
    public function update(User $user, ExpiringDocument $document): bool
    {
        return ! $user->isPlatformAdmin()
            && $this->owns($document)
            && ! $document->trashed()
            && $user->checkPermissionTo('core.documents.update')
            && $this->canWriteOperationalData();
    }

    public function renew(User $user, ExpiringDocument $document): bool
    {
        return ! $user->isPlatformAdmin()
            && $this->owns($document)
            && $document->is_current
            && ! $document->trashed()
            && $user->checkPermissionTo('core.documents.create')
            && $this->canWriteOperationalData();
    }

    /**
     * Soft delete only (legal retention), with its own permission.
     */
    public function delete(User $user, ExpiringDocument $document): bool
    {
        return ! $user->isPlatformAdmin()
            && $this->owns($document)
            && $user->checkPermissionTo('core.documents.delete')
            && $this->canWriteOperationalData();
    }

    public function restore(User $user, ExpiringDocument $document): bool
    {
        return $this->delete($user, $document);
    }

    public function forceDelete(User $user, ExpiringDocument $document): bool
    {
        return false;
    }

    private function owns(ExpiringDocument $document): bool
    {
        return CompanyContext::id() !== null && $document->company_id === CompanyContext::id();
    }
}
