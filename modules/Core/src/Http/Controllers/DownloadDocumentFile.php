<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Models\ExpiringDocumentFile;
use Modules\Core\Support\DocumentFileStore;

/**
 * /app/{tenant}/documentos/archivos/{file}: authorizes the download and
 * redirects to a short-lived signed URL of the private file. The file is
 * looked up inside the company scope (no implicit binding: it would run
 * before the tenant middleware sets the company), so another company's
 * file is a 404.
 */
final class DownloadDocumentFile
{
    public function __invoke(Request $request, DocumentFileStore $store): RedirectResponse
    {
        $record = ExpiringDocumentFile::query()->findOrFail((string) $request->route('file'));

        Gate::authorize('view', $record);

        return redirect()->away($store->temporaryUrl($record));
    }
}
