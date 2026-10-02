<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Licensing\ModuleAccess;
use Symfony\Component\HttpFoundation\Response;

/**
 * 403 when the active company has no license for the module, and on writes
 * (non-GET requests) when the license only allows reading.
 * Alias: module.licensed:<code>. Applied automatically to the routes of
 * licensable modules.
 */
final class EnsureModuleIsLicensed
{
    public function __construct(
        private readonly ModuleAccess $access,
    ) {}

    public function handle(Request $request, Closure $next, string $moduleCode): Response
    {
        $level = $this->access->currentLevel($moduleCode);

        abort_unless($level->canRead(), Response::HTTP_FORBIDDEN);
        abort_if(! $request->isMethodSafe() && ! $level->canWrite(), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
