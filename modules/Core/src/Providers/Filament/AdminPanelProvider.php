<?php

declare(strict_types=1);

namespace Modules\Core\Providers\Filament;

use App\Modules\ModuleServiceProvider;
use Filament\Panel;
use Illuminate\Support\Facades\Route;
use Modules\Core\Filament\Tenancy\EditCompanyProfile;
use Modules\Core\Http\Controllers\DownloadDocumentFile;
use Modules\Core\Http\Middleware\ApplyTenantContext;
use Modules\Core\Models\Company;

/**
 * Company panel at /app/{company}. The company is the Filament tenant: it is
 * part of the URL and switched from the sidebar. Modules add their own
 * resources, pages, widgets and navigation group from their providers.
 */
final class AdminPanelProvider extends IntegraPanelProvider
{
    public const string DOCUMENT_FILE_ROUTE = 'documents.files.show';

    public function panel(Panel $panel): Panel
    {
        return $this->configure($panel)
            ->default()
            ->id(ModuleServiceProvider::ADMIN_PANEL_ID)
            ->path('app')
            ->tenant(Company::class)
            ->tenantProfile(EditCompanyProfile::class)
            ->tenantMiddleware([
                ApplyTenantContext::class,
            ], isPersistent: true)
            ->authenticatedTenantRoutes(function (): void {
                Route::get('documentos/archivos/{file}', DownloadDocumentFile::class)
                    ->name(self::DOCUMENT_FILE_ROUTE);
            });
    }
}
