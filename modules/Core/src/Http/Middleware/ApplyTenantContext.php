<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use App\Support\Tenancy\CompanyContext;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Modules\Core\Models\Company;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filament tenant middleware of /app: the company in the URL (already
 * checked by Filament with User::canAccessTenant()) becomes the active
 * company for the rest of the request, and the session remembers it.
 */
final class ApplyTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Company, Response::HTTP_NOT_FOUND);

        CompanyContext::activate($tenant);

        if ($request->hasSession()) {
            $request->session()->put('company_id', $tenant->getKey());
        }

        return $next($request);
    }
}
