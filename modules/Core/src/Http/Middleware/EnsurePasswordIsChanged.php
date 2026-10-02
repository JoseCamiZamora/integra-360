<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users with a temporary password must change it before anything else.
 * Alias: password.changed.
 */
final class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->must_change_password) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
