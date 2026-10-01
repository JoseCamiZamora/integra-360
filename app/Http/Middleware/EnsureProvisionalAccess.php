<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Temporary gate for /app and /conductor until I360-01 adds authentication.
 * Remove it from the panel and driver routes once real login exists.
 */
final class EnsureProvisionalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = app()->environment(['local', 'testing'])
            || config('integra.provisional_access') === true;

        abort_unless($allowed, Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
