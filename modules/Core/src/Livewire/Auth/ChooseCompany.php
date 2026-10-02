<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Actions\ResolveHomeUrl;
use Modules\Core\Models\Company;
use Modules\Core\Models\User;

/**
 * Users of several companies choose the active one after signing in. Inside
 * the panel they switch from the sidebar.
 */
#[Layout('layouts::driver')]
final class ChooseCompany extends Component
{
    public function choose(string $companyId, ResolveHomeUrl $home): void
    {
        $company = $this->user()->activeCompanies()->firstWhere('id', $companyId);

        abort_unless($company instanceof Company, 403);

        session()->put('company_id', $company->getKey());
        session()->forget('interface');

        $this->redirect($home->handle($this->user(), session()->driver()));
    }

    public function render(): View
    {
        return view('core::livewire.auth.choose-company', [
            'companies' => $this->user()->activeCompanies(),
        ])->title(__('core::auth.choose_company.title'));
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
