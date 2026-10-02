<?php

declare(strict_types=1);

namespace Modules\Core\Livewire;

use App\Support\Tenancy\CompanyContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Actions\ResolveHomeUrl;
use Modules\Core\Models\Company;
use Modules\Core\Models\User;

/**
 * Driver home (/conductor). The vehicle card and the inspection button are
 * added by the PESV prompts.
 */
#[Layout('layouts::driver')]
final class DriverHome extends Component
{
    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $company = Company::query()->findOrFail(CompanyContext::requireId());

        return view('core::livewire.driver-home', [
            'firstName' => Str::of($user->name)->before(' ')->value(),
            'company' => $company,
            'today' => Str::ucfirst(now()->translatedFormat(__('core::driver.date_format'))),
            'panelUrl' => $user->checkPermissionTo('core.panel.access') ? app(ResolveHomeUrl::class)->panelUrl($company) : null,
        ])->title(__('core::driver.title'));
    }
}
