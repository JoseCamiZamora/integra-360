<?php

declare(strict_types=1);

namespace Modules\Core\Listeners;

use App\Support\Tenancy\CompanyScopeBypassed;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Modules\Core\Enums\AuditEvent;
use Modules\Core\Models\User;
use Modules\Core\Support\UserAuditLog;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Role;

/**
 * Audit log entries that are not plain model changes: sign-ins, password
 * resets by e-mail, role assignments and cross-company queries.
 *
 * Account events go through UserAuditLog (one entry per company of the user).
 * Passwords never reach the log: only the identifier typed is kept for
 * failed attempts.
 */
final class RecordAuditEvents
{
    public function login(Login $event): void
    {
        if ($event->user instanceof User) {
            UserAuditLog::record($event->user, AuditEvent::Login, causer: $event->user);
        }
    }

    public function failed(Failed $event): void
    {
        $properties = ['identifier' => (string) ($event->credentials['identifier'] ?? '')];

        if ($event->user instanceof User) {
            UserAuditLog::record($event->user, AuditEvent::LoginFailed, properties: $properties);

            return;
        }

        activity()
            ->event(AuditEvent::LoginFailed->value)
            ->withProperties($properties)
            ->log(AuditEvent::LoginFailed->value);
    }

    public function passwordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            UserAuditLog::record($event->user, AuditEvent::PasswordResetByEmail, causer: $event->user);
        }
    }

    public function roleAttached(RoleAttachedEvent $event): void
    {
        $this->logRoles($event->model, $event->rolesOrIds, AuditEvent::RoleAssigned);
    }

    public function roleDetached(RoleDetachedEvent $event): void
    {
        $this->logRoles($event->model, $event->rolesOrIds, AuditEvent::RoleRemoved);
    }

    public function scopeBypassed(CompanyScopeBypassed $event): void
    {
        // One entry per model and request: a table builds its query several times.
        $key = 'audit.scope_bypassed.'.$event->model;

        if (request()->attributes->has($key)) {
            return;
        }

        request()->attributes->set($key, true);

        activity()
            ->event(AuditEvent::ScopeBypassed->value)
            ->withProperties(['model' => class_basename($event->model)])
            ->tap(function (Model $activity): void {
                // A cross-company query is a platform-level event.
                $activity->setAttribute('company_id', null);
            })
            ->log(AuditEvent::ScopeBypassed->value);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'login',
            Failed::class => 'failed',
            PasswordReset::class => 'passwordReset',
            RoleAttachedEvent::class => 'roleAttached',
            RoleDetachedEvent::class => 'roleDetached',
            CompanyScopeBypassed::class => 'scopeBypassed',
        ];
    }

    private function logRoles(mixed $model, mixed $rolesOrIds, AuditEvent $event): void
    {
        if (! $model instanceof User) {
            return;
        }

        $ids = collect(is_iterable($rolesOrIds) ? $rolesOrIds : [$rolesOrIds])
            ->map(fn (mixed $role): mixed => $role instanceof Role ? $role->getKey() : $role)
            ->all();

        activity()
            ->performedOn($model)
            ->event($event->value)
            ->withProperties(['roles' => Role::query()->whereKey($ids)->pluck('name')->all()])
            ->log($event->value);
    }
}
