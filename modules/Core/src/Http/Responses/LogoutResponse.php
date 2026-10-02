<?php

declare(strict_types=1);

namespace Modules\Core\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Http\RedirectResponse;

/**
 * After signing out from a Filament panel, go back to the single sign-in
 * screen (/ingreso) instead of a panel URL.
 */
final class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        return redirect()->route('login');
    }
}
