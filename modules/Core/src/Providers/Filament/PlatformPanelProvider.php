<?php

declare(strict_types=1);

namespace Modules\Core\Providers\Filament;

use Filament\Panel;
use Modules\Core\Models\User;

/**
 * Platform owner's panel at /plataforma: companies, licenses and each
 * company's first administrator. Only for is_platform_admin users
 * (User::canAccessPanel()). It has no active company: whatever touches a
 * company's data runs inside CompanyContext::run().
 */
final class PlatformPanelProvider extends IntegraPanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $path = dirname(__DIR__, 2).'/Filament/Platform';

        return $this->configure($panel)
            ->id(User::PLATFORM_PANEL_ID)
            ->path('plataforma')
            ->discoverResources(in: $path.'/Resources', for: 'Modules\\Core\\Filament\\Platform\\Resources')
            ->discoverPages(in: $path.'/Pages', for: 'Modules\\Core\\Filament\\Platform\\Pages');
    }
}
