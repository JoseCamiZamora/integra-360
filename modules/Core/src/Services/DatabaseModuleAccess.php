<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use App\Modules\ModuleRegistry;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Enums\ModuleAccessLevel;
use Modules\Core\Licensing\ModuleAccess;
use Modules\Core\Licensing\ModuleLimits;
use Modules\Core\Models\Company;
use Modules\Core\Models\ModuleLicense;

/**
 * ModuleAccess backed by module_licenses. Bound as a scoped instance, so its
 * memo lives for one request or job at most.
 */
final class DatabaseModuleAccess implements ModuleAccess
{
    /**
     * @var array<string, ModuleLicense|null>
     */
    private array $bestLicense = [];

    public function __construct(
        private readonly ModuleRegistry $registry,
    ) {}

    public function level(Company $company, string $moduleCode): ModuleAccessLevel
    {
        if (! $company->is_active) {
            return ModuleAccessLevel::None;
        }

        if ($this->isAlwaysOn($moduleCode)) {
            return ModuleAccessLevel::Full;
        }

        return $this->bestLicense($company, $moduleCode)?->accessLevel() ?? ModuleAccessLevel::None;
    }

    public function isEnabled(Company $company, string $moduleCode): bool
    {
        return $this->level($company, $moduleCode)->canRead();
    }

    public function canWrite(Company $company, string $moduleCode): bool
    {
        return $this->level($company, $moduleCode)->canWrite();
    }

    public function limits(Company $company, string $moduleCode): ModuleLimits
    {
        if ($this->isAlwaysOn($moduleCode)) {
            return ModuleLimits::unlimited();
        }

        $license = $this->bestLicense($company, $moduleCode);

        if ($license === null || ! $license->accessLevel()->canRead()) {
            return ModuleLimits::none();
        }

        return new ModuleLimits($license->max_vehicles, $license->max_people);
    }

    public function currentLevel(string $moduleCode): ModuleAccessLevel
    {
        $companyId = CompanyContext::id();
        $company = $companyId === null ? null : Company::query()->find($companyId);

        return $company === null ? ModuleAccessLevel::None : $this->level($company, $moduleCode);
    }

    public function companiesWithWriteAccess(string $moduleCode): Collection
    {
        return Company::query()
            ->where('is_active', true)
            ->orderBy('legal_name')
            ->get()
            ->filter(fn (Company $company): bool => $this->canWrite($company, $moduleCode))
            ->values()
            ->toBase();
    }

    /**
     * Core (and any module that is not licensable) is always available.
     */
    private function isAlwaysOn(string $moduleCode): bool
    {
        return $this->registry->has($moduleCode) && ! $this->registry->get($moduleCode)->licensable;
    }

    /**
     * The license that grants the most access today (latest expiry first).
     */
    private function bestLicense(Company $company, string $moduleCode): ?ModuleLicense
    {
        $key = $company->getKey().'|'.$moduleCode.'|'.Carbon::today()->toDateString();

        if (array_key_exists($key, $this->bestLicense)) {
            return $this->bestLicense[$key];
        }

        $licenses = CompanyContext::run($company, fn () => ModuleLicense::query()
            ->where('module_code', $moduleCode)
            ->get());

        $rank = fn (ModuleLicense $license): int => match ($license->accessLevel()) {
            ModuleAccessLevel::Full => 2,
            ModuleAccessLevel::ReadOnly => 1,
            ModuleAccessLevel::None => 0,
        };

        return $this->bestLicense[$key] = $licenses
            ->sortByDesc(fn (ModuleLicense $license): string => $rank($license).'|'.($license->ends_at?->toDateString() ?? '9999-12-31'))
            ->first();
    }
}
