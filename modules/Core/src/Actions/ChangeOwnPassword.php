<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use Modules\Core\Enums\AuditEvent;
use Modules\Core\Models\User;
use Modules\Core\Support\UserAuditLog;

/**
 * The user sets their own password (mandatory after a temporary one).
 */
final class ChangeOwnPassword
{
    public function handle(User $user, #[\SensitiveParameter] string $password): void
    {
        $user->forceFill([
            'password' => $password,
            'must_change_password' => false,
        ])->save();

        UserAuditLog::record($user, AuditEvent::PasswordChanged, causer: $user);
    }
}
