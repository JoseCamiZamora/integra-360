<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Platform\Resources\LicenseCategories\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Core\Filament\Platform\Resources\LicenseCategories\LicenseCategoryResource;

final class ManageLicenseCategories extends ManageRecords
{
    protected static string $resource = LicenseCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
