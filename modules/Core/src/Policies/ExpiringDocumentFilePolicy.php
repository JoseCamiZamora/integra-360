<?php

declare(strict_types=1);

namespace Modules\Core\Policies;

use App\Support\Tenancy\CompanyContext;
use Modules\Core\Models\ExpiringDocument;
use Modules\Core\Models\ExpiringDocumentFile;
use Modules\Core\Models\User;

/**
 * Downloading a document file: whoever may view the document and, for
 * sensitive types (occupational medical exam...), also has
 * core.documents.view-sensitive. Files of deleted documents are not served.
 * The platform administrator is denied through ExpiringDocumentPolicy::view().
 */
final class ExpiringDocumentFilePolicy
{
    public function view(User $user, ExpiringDocumentFile $file): bool
    {
        if (CompanyContext::id() === null || $file->company_id !== CompanyContext::id()) {
            return false;
        }

        $document = $file->document;

        if (! $document instanceof ExpiringDocument || ! $user->can('view', $document)) {
            return false;
        }

        return ! $document->documentType->is_sensitive
            || $user->checkPermissionTo('core.documents.view-sensitive');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ExpiringDocumentFile $file): bool
    {
        return false;
    }

    public function delete(User $user, ExpiringDocumentFile $file): bool
    {
        return false;
    }
}
