<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Modules\Core\Enums\AuditEvent;
use Modules\Core\Models\User;
use Modules\Core\Support\TemporaryCredentials;
use Modules\Core\Support\TemporaryPassword;
use Modules\Core\Support\UserAuditLog;

/**
 * A company administrator gives a user a new temporary password (for users
 * without e-mail). Recorded in the audit log; the password is not.
 */
final class ResetUserPassword
{
    public function handle(User $user, User $administrator): TemporaryCredentials
    {
        $password = TemporaryPassword::generate();

        $user->forceFill([
            'password' => $password,
            'must_change_password' => true,
            'remember_token' => null,
        ])->save();

        UserAuditLog::record($user, AuditEvent::PasswordResetByAdmin, causer: $administrator);

        return new TemporaryCredentials($user, $password);
    }
}
