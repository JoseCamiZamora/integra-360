<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

/**
 * Event types stored in activity_log.event. Model changes use spatie's
 * created/updated/deleted/restored; the rest are logged by Core.
 */
enum AuditEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case Login = 'login';
    case LoginFailed = 'login_failed';
    case Lockout = 'lockout';
    case PasswordChanged = 'password_changed';
    case PasswordResetByAdmin = 'password_reset_by_admin';
    case PasswordResetByEmail = 'password_reset_by_email';
    case RoleAssigned = 'role_assigned';
    case RoleRemoved = 'role_removed';
    case ScopeBypassed = 'scope_bypassed';

    public function label(): string
    {
        return __('core::audit.events.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public static function labelFor(?string $event): string
    {
        return $event === null ? '' : (self::tryFrom($event)?->label() ?? $event);
    }
}
