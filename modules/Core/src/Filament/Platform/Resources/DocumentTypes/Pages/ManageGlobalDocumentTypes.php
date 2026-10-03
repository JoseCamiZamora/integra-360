<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Platform\Resources\DocumentTypes\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\Platform\Resources\DocumentTypes\GlobalDocumentTypeResource;
use Modules\Core\Models\DocumentType;

final class ManageGlobalDocumentTypes extends ManageRecords
{
    protected static string $resource = GlobalDocumentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(fn (array $data): Model => DocumentType::createGlobal($data)),
        ];
    }
}
