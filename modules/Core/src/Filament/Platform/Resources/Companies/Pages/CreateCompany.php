<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Platform\Resources\Companies\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Actions\CreateCompany as CreateCompanyAction;
use Modules\Core\Filament\Platform\Resources\Companies\CompanyResource;

final class CreateCompany extends CreateRecord
{
    protected static string $resource = CompanyResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateCompanyAction::class)->handle($data);
    }

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
