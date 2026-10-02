<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Tenancy;

use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Schemas\CompanyForm;

/**
 * "Mi empresa": general data and logo of the active company. Only users with
 * core.company.update (company_admin) can open it (CompanyPolicy::update).
 */
final class EditCompanyProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return __('core::companies.profile');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(CompanyForm::components(forPlatform: false));
    }
}
