<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use App\Support\Tenancy\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Modules\Core\Models\Company;
use Modules\Core\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Routes outside Filament (/conductor, module routes): the company chosen at
 * sign-in (session "company_id") becomes the active company, as long as the
 * user is still an active member of it. Alias: company.active.
 */
final class EnsureActiveCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();
        $companies = $user->activeCompanies();

        $company = $companies->firstWhere('id', $request->session()->get('company_id'));

        if (! $company instanceof Company && $companies->count() === 1) {
            $company = $companies->first();
        }

        if (! $company instanceof Company) {
            $request->session()->forget('company_id');

            return redirect()->route('company.choose');
        }

        $request->session()->put('company_id', $company->getKey());
        CompanyContext::activate($company);

        return $next($request);
    }
}
