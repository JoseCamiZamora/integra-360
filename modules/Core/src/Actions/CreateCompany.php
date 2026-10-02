<?php

declare(strict_types=1);

namespace Modules\Core\Actions;

use App\Support\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Company;
use Modules\Core\Support\Nit;

/**
 * Creates a company together with its main branch (every company has one).
 */
final class CreateCompany
{
    /**
     * @param  array<string, mixed>  $data  Company attributes
     */
    public function handle(array $data): Company
    {
        return DB::transaction(function () use ($data): Company {
            $data['nit'] = Nit::normalize((string) $data['nit']) ?? $data['nit'];

            $company = Company::query()->create($data);

            CompanyContext::run($company, function () use ($company): void {
                $branch = new Branch([
                    'name' => __('core::branches.main_default_name'),
                    'city' => $company->city,
                    'address' => $company->address,
                    'phone' => $company->phone,
                    'is_active' => true,
                ]);
                $branch->is_main = true;
                $branch->save();
            });

            return $company;
        });
    }
}
