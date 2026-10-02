<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use App\Modules\ModuleServiceProvider;
use App\Support\Tenancy\CompanyContext;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Actions\ResolveHomeUrl;
use Modules\Core\Console\SyncPermissionsCommand;
use Modules\Core\Http\Middleware\EnsureActiveCompany;
use Modules\Core\Http\Middleware\EnsureModuleIsLicensed;
use Modules\Core\Http\Middleware\EnsurePasswordIsChanged;
use Modules\Core\Http\Responses\LogoutResponse;
use Modules\Core\Licensing\ModuleAccess;
use Modules\Core\Listeners\RecordAuditEvents;
use Modules\Core\Models\AuditEntry;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Company;
use Modules\Core\Models\Membership;
use Modules\Core\Models\ModuleLicense;
use Modules\Core\Models\User;
use Modules\Core\Policies\AuditEntryPolicy;
use Modules\Core\Policies\BranchPolicy;
use Modules\Core\Policies\CompanyPolicy;
use Modules\Core\Policies\ModuleLicensePolicy;
use Modules\Core\Policies\UserPolicy;
use Modules\Core\Services\DatabaseModuleAccess;
use Spatie\Activitylog\Actions\LogActivityAction;
use Spatie\Activitylog\Contracts\Activity;

final class CoreServiceProvider extends ModuleServiceProvider
{
    public function code(): string
    {
        return 'core';
    }

    public function register(): void
    {
        parent::register();

        CompanyContext::useCompanyModel(Company::class);

        // Routes of licensable modules: signed in, password changed, active
        // company and a license that allows the request.
        ModuleServiceProvider::guardLicensableRoutesWith(fn (string $code): array => [
            'auth', 'password.changed', 'company.active', 'module.licensed:'.$code,
        ]);

        $this->app->scoped(ModuleAccess::class, DatabaseModuleAccess::class);
        $this->app->bind(LogoutResponseContract::class, LogoutResponse::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerMiddleware();
        $this->registerAuthorization();
        $this->registerAuthRedirects();
        $this->registerAuditLog();

        // A license change must be visible within the same request.
        ModuleLicense::saved(fn () => $this->app->forgetInstance(ModuleAccess::class));

        if ($this->app->runningInConsole()) {
            $this->commands([SyncPermissionsCommand::class]);
        }
    }

    private function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('company.active', EnsureActiveCompany::class);
        $router->aliasMiddleware('module.licensed', EnsureModuleIsLicensed::class);
        $router->aliasMiddleware('password.changed', EnsurePasswordIsChanged::class);
    }

    private function registerAuthorization(): void
    {
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ModuleLicense::class, ModuleLicensePolicy::class);
        Gate::policy(AuditEntry::class, AuditEntryPolicy::class);

        // Explicit rule for the platform administrator: the only one who may
        // query outside the company scope (always audited).
        Gate::define(CompanyContext::BYPASS_ABILITY, fn (User $user): bool => $user->isPlatformAdmin());
    }

    /**
     * Single sign-in screen (/ingreso) for every interface, including the
     * Filament panels, which have no login page of their own.
     */
    private function registerAuthRedirects(): void
    {
        $toLogin = fn (): string => route('login');

        Authenticate::redirectUsing($toLogin);
        AuthenticationException::redirectUsing($toLogin);

        RedirectIfAuthenticated::redirectUsing(fn (Request $request): string => $this->app->make(ResolveHomeUrl::class)
            ->handle($request->user(), $request->session()));
    }

    /**
     * Every audit entry records its company (the subject's, else the active
     * one) and the request IP, unless the logger set the company explicitly.
     */
    private function registerAuditLog(): void
    {
        Event::subscribe(RecordAuditEvents::class);

        LogActivityAction::clearBeforeLoggingCallbacks();
        LogActivityAction::beforeLogging(function (Activity $activity): void {
            if (! $activity instanceof AuditEntry) {
                return;
            }

            if (! array_key_exists('company_id', $activity->getAttributes())) {
                $activity->setAttribute('company_id', $this->auditCompanyId($activity));
            }

            if (! $this->app->runningInConsole() || $this->app->runningUnitTests()) {
                $activity->setAttribute('ip_address', request()->ip());
            }

            $label = $this->auditSubjectLabel($activity->getRelationValue('subject'));

            if ($label !== null) {
                $activity->properties = ($activity->properties ?? collect())->put('subject_label', $label);
            }
        });
    }

    /**
     * Stored with the entry, so the audit list never has to load subjects
     * (possibly of another company, or deleted).
     */
    private function auditSubjectLabel(mixed $subject): ?string
    {
        return match (true) {
            $subject instanceof Company => $subject->displayName(),
            $subject instanceof User, $subject instanceof Branch => $subject->name,
            $subject instanceof Membership => User::query()->find($subject->user_id)?->name,
            $subject instanceof ModuleLicense => $subject->module_code,
            default => null,
        };
    }

    private function auditCompanyId(Model $activity): ?string
    {
        $subject = $activity->getRelationValue('subject');

        return match (true) {
            $subject instanceof Company => $subject->getKey(),
            $subject instanceof Model && filled($subject->getAttribute('company_id')) => (string) $subject->getAttribute('company_id'),
            default => CompanyContext::id(),
        };
    }
}
