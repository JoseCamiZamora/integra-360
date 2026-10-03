<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\DocumentTypes\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Core\Filament\Resources\DocumentTypes\DocumentTypeResource;

final class ManageDocumentTypes extends ManageRecords
{
    protected static string $resource = DocumentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
