<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Documents\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Resources\Documents\ExpiringDocumentResource;

final class ListExpiringDocuments extends ListRecords
{
    protected static string $resource = ExpiringDocumentResource::class;
}
