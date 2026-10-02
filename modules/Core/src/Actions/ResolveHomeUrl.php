<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use App\Modules\ModuleServiceProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Session\Session;
use Modules\Core\Models\Company;
use Modules\Core\Models\User;

/**
 * Where a signed-in user goes: password change, platform panel, company
 * selection, interface selection (driver + another role), the driver view
 * or the company panel.
 */
final class ResolveHomeUrl
{
    public const string INTERFACE_DRIVER = 'driver';

    public const string INTERFACE_PANEL = 'panel';

    public function handle(User $user, Session $session): string
    {
        if ($user->must_change_password) {
            return route('password.change');
        }

        if ($user->isPlatformAdmin()) {
            return Filament::getPanel(User::PLATFORM_PANEL_ID)->getUrl() ?? '/';
        }

        $company = $this->activeCompany($user, $session);

        if ($company === null) {
            return route('company.choose');
        }

        $driver = $user->hasPermissionInCompany($company, 'core.driver.access');
        $panel = $user->hasPermissionInCompany($company, 'core.panel.access');

        if ($driver && $panel) {
            return match ($session->get('interface')) {
                self::INTERFACE_DRIVER => route('driver.home'),
                self::INTERFACE_PANEL => $this->panelUrl($company),
                default => route('interface.choose'),
            };
        }

        return match (true) {
            $driver => route('driver.home'),
            $panel => $this->panelUrl($company),
            default => route('no-access'),
        };
    }

    public function panelUrl(Company $company): string
    {
        return Filament::getPanel(ModuleServiceProvider::ADMIN_PANEL_ID)->getUrl($company) ?? '/';
    }

    private function activeCompany(User $user, Session $session): ?Company
    {
        $companies = $user->activeCompanies();
        $company = $companies->firstWhere('id', $session->get('company_id'));

        if (! $company instanceof Company && $companies->count() === 1) {
            $company = $companies->first();
        }

        if ($company instanceof Company) {
            $session->put('company_id', $company->getKey());

            return $company;
        }

        $session->forget('company_id');

        return null;
    }
}
