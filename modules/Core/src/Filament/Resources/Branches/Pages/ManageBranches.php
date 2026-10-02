<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Branches\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Core\Filament\Resources\Branches\BranchResource;

final class ManageBranches extends ManageRecords
{
    protected static string $resource = BranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
