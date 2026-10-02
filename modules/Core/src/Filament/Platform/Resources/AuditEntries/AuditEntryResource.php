<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Platform\Resources\AuditEntries;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Filament\Platform\Resources\AuditEntries\Pages\ListAuditEntries;
use Modules\Core\Filament\Tables\AuditTable;
use Modules\Core\Models\AuditEntry;
use Modules\Core\Models\User;

/**
 * Platform panel: the audit log of every company. Reading it is an explicit
 * cross-company query (withoutCompanyScope()), which is itself audited.
 */
final class AuditEntryResource extends Resource
{
    protected static ?string $model = AuditEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

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

    /**
     * @return Builder<AuditEntry>
     */
    public static function getEloquentQuery(): Builder
    {
        return AuditEntry::query()->withoutCompanyScope();
    }

    public static function table(Table $table): Table
    {
        return AuditTable::configure(
            $table,
            fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all(),
            showCompany: true,
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditEntries::route('/'),
        ];
    }
}
