<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use App\Support\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Enums\AuditEvent;
use Modules\Core\Models\User;

/**
 * Audit entries about a user account (sign-ins, password changes). Inside a
 * company context they belong to that company; otherwise (sign-in screens)
 * one entry is written per company of the user, so every company
 * administrator sees what happened to their own users.
 */
final class UserAuditLog
{
    /**
     * @param  array<string, mixed>  $properties  never passwords
     */
    public static function record(User $user, AuditEvent $event, ?User $causer = null, array $properties = []): void
    {
        $companyIds = CompanyContext::check()
            ? [CompanyContext::id()]
            : $user->companies()->pluck('companies.id')->all();

        foreach ($companyIds === [] ? [null] : $companyIds as $companyId) {
            activity()
                ->performedOn($user)
                ->causedBy($causer)
                ->event($event->value)
                ->withProperties($properties)
                ->tap(function (Model $activity) use ($companyId): void {
                    $activity->setAttribute('company_id', $companyId);
                })
                ->log($event->value);
        }
    }
}
