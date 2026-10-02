<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\AuditEntries;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\Resources\AuditEntries\Pages\ListAuditEntries;
use Modules\Core\Filament\Tables\AuditTable;
use Modules\Core\Models\AuditEntry;
use Modules\Core\Models\User;
use UnitEnum;

/**
 * Read-only audit log of the active company (company_admin).
 */
final class AuditEntryResource extends Resource
{
    protected static ?string $model = AuditEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'core';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'audit';

    public static function getModelLabel(): string
    {
        return __('core::audit.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::audit.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('core::audit.navigation');
    }

    public static function table(Table $table): Table
    {
        return AuditTable::configure(
            $table,
            fn (): array => User::query()->inActiveCompany()->orderBy('name')->pluck('name', 'id')->all(),
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditEntries::route('/'),
        ];
    }
}
