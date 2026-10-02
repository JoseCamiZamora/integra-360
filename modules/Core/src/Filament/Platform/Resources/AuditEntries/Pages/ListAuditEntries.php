<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Platform\Resources\AuditEntries\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Platform\Resources\AuditEntries\AuditEntryResource;

final class ListAuditEntries extends ListRecords
{
    protected static string $resource = AuditEntryResource::class;
}
