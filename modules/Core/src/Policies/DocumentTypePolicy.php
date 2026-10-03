<?php

declare(strict_types=1);

namespace Modules\Core\Policies;

use App\Support\Tenancy\CompanyContext;
use Modules\Core\Models\DocumentType;
use Modules\Core\Models\User;
use Modules\Core\Policies\Concerns\ChecksOperationalWriteAccess;

/**
 * Global types (company_id NULL): only the platform administrator manages
 * them, from the platform panel; companies can read them. Company types:
 * the company administrator (core.document-types.*), only of the active
 * company. Explicit rule for the platform administrator: global types only,
 * never a company's own types.
 *
 * Types are deactivated, never deleted: documents keep pointing at them.
 */
final class DocumentTypePolicy
{
    use ChecksOperationalWriteAccess;

    public function viewAny(User $user): bool
    {
        return $user->isPlatformAdmin() || $user->checkPermissionTo('core.document-types.view');
    }

    public function view(User $user, DocumentType $type): bool
    {
        if ($user->isPlatformAdmin()) {
            return $type->isGlobal();
        }

        return ($type->isGlobal() || $this->owns($type)) && $user->checkPermissionTo('core.document-types.view');
    }

    /**
     * The platform administrator creates global types (outside any company);
     * company users create types of the active company.
     */
    public function create(User $user): bool
    {
        if ($user->isPlatformAdmin()) {
            return ! CompanyContext::check();
        }

        return CompanyContext::check() && $user->checkPermissionTo('core.document-types.create')
            && $this->canWriteOperationalData();
    }

    public function update(User $user, DocumentType $type): bool
    {
        if ($user->isPlatformAdmin()) {
            return $type->isGlobal();
        }

        return $this->owns($type) && $user->checkPermissionTo('core.document-types.update')
            && $this->canWriteOperationalData();
    }

    public function delete(User $user, DocumentType $type): bool
    {
        return false;
    }

    private function owns(DocumentType $type): bool
    {
        return CompanyContext::id() !== null && $type->company_id === CompanyContext::id();
    }
}
