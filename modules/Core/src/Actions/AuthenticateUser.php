<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\SessionGuard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\User;

/**
 * Signs a user in with their document number or e-mail (same field).
 *
 * - 5 failed attempts per minute per identifier and IP.
 * - Users of inactive companies cannot sign in.
 * - "Remember me" lasts 30 days, only for users whose only role is driver
 *   (one guard; the duration is set at sign-in, decision 4). Everyone else
 *   gets a standard session.
 */
final class AuthenticateUser
{
    public const int MAX_ATTEMPTS = 5;

    public const int DECAY_SECONDS = 60;

    public const int DRIVER_REMEMBER_MINUTES = 30 * 24 * 60;

    public function handle(
        string $identifier,
        #[\SensitiveParameter] string $password,
        bool $remember,
        Request $request,
    ): User {
        $identifier = trim($identifier);
        $key = $this->throttleKey($identifier, (string) $request->ip());

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            event(new Lockout($request));

            throw ValidationException::withMessages([
                'identifier' => __('core::auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $user = $this->findUser($identifier, $password);

        if ($user === null) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            event(new Failed('web', $this->knownUser($identifier), ['identifier' => $identifier]));

            throw ValidationException::withMessages(['identifier' => __('core::auth.failed')]);
        }

        if (! $user->isPlatformAdmin() && $user->activeCompanies()->isEmpty()) {
            throw ValidationException::withMessages(['identifier' => __('core::auth.no_active_company')]);
        }

        RateLimiter::clear($key);

        $longSession = $remember && $user->isOnlyDriver();
        $guard = Auth::guard('web');

        if ($longSession && $guard instanceof SessionGuard) {
            $guard->setRememberDuration(self::DRIVER_REMEMBER_MINUTES);
        }

        $guard->login($user, $longSession);

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return $user;
    }

    public function throttleKey(string $identifier, string $ip): string
    {
        return 'login:'.Str::lower($this->normalize($identifier)).'|'.$ip;
    }

    private function findUser(string $identifier, #[\SensitiveParameter] string $password): ?User
    {
        // A document number may exist with two document types (CC and TI):
        // the password decides which account it is.
        foreach ($this->candidates($identifier) as $user) {
            if (Hash::check($password, $user->password)) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Only to attach failed attempts to the account's companies in the
     * audit log; never revealed to the person signing in.
     */
    private function knownUser(string $identifier): ?User
    {
        $candidates = $this->candidates($identifier);

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    /**
     * @return Collection<int, User>
     */
    private function candidates(string $identifier): Collection
    {
        $identifier = $this->normalize($identifier);

        if ($identifier === '') {
            return new Collection;
        }

        return str_contains($identifier, '@')
            ? User::query()->where('email', $identifier)->get()
            : User::query()->where('document_number', $identifier)->get();
    }

    /**
     * Documents are typed with or without dots and spaces ("1.020.304").
     */
    private function normalize(string $identifier): string
    {
        $identifier = trim($identifier);

        return str_contains($identifier, '@')
            ? Str::lower($identifier)
            : (string) preg_replace('/[\s.\-]/', '', $identifier);
    }
}
