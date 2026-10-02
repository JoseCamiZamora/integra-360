<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Users\Pages;

use App\Support\Tenancy\CompanyContext;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Actions\CreateCompanyUser;
use Modules\Core\Filament\Resources\Users\UserResource;
use Modules\Core\Models\Company;

final class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('core::users.actions.create'))
                ->createAnother(false)
                ->successNotification(null)
                ->using(function (array $data, CreateCompanyUser $create): Model {
                    $company = Company::query()->findOrFail(CompanyContext::requireId());
                    $credentials = $create->handle($company, $data, array_values($data['roles']));

                    UserResource::notifyTemporaryPassword($credentials);

                    return $credentials->user;
                }),
        ];
    }
}
