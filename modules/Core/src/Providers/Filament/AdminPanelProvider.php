<?php

declare(strict_types=1);

namespace Modules\Core\Providers\Filament;

use App\Modules\ModuleServiceProvider;
use Filament\Panel;
use Modules\Core\Filament\Tenancy\EditCompanyProfile;
use Modules\Core\Http\Middleware\ApplyTenantContext;
use Modules\Core\Models\Company;

/**
 * Company panel at /app/{company}. The company is the Filament tenant: it is
 * part of the URL and switched from the sidebar. Modules add their own
 * resources, pages, widgets and navigation group from their providers.
 */
final class AdminPanelProvider extends IntegraPanelProvider
{
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
            ], isPersistent: true);
    }
}
