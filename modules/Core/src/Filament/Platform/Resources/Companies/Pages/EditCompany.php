<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Platform\Resources\Companies\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Actions\CreateCompanyUser;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Filament\Platform\Resources\Companies\CompanyResource;
use Modules\Core\Filament\Resources\Users\UserResource;
use Modules\Core\Models\Company;

/**
 * Company data and licenses (relation manager), plus "Crear administrador":
 * the company's first company_admin, with a temporary password shown once.
 */
final class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createAdmin')
                ->label(__('core::companies.actions.create_admin'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->authorize('update')
                ->schema(fn (Schema $schema): Schema => $schema
                    ->columns(['default' => 1, 'md' => 2])
                    ->components(UserResource::identityFields()))
                ->action(function (array $data, CreateCompanyUser $create): void {
                    /** @var Company $company */
                    $company = $this->getRecord();

                    UserResource::notifyTemporaryPassword(
                        $create->handle($company, $data, [CompanyRole::CompanyAdmin->value]),
                    );
                }),
        ];
    }
}
