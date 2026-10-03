<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Platform\Resources\DocumentTypes;

use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Filament\Platform\Resources\DocumentTypes\Pages\ManageGlobalDocumentTypes;
use Modules\Core\Filament\Support\DocumentTypeForm;
use Modules\Core\Models\DocumentType;

/**
 * Global document types (/plataforma): shared by every company, managed only
 * by the platform administrator. No company context here: globalOnly().
 */
final class GlobalDocumentTypeResource extends Resource
{
    protected static ?string $model = DocumentType::class;

    protected static ?string $slug = 'document-types';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('core::document_types.model_global');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::document_types.plural_global');
    }

    /**
     * @return Builder<DocumentType>
     */
    public static function getEloquentQuery(): Builder
    {
        return DocumentType::query()->globalOnly();
    }

    public static function form(Schema $schema): Schema
    {
        return DocumentTypeForm::configure($schema, fn () => DocumentType::query()->globalOnly());
    }

    public static function table(Table $table): Table
    {
        return DocumentTypeForm::table($table)
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGlobalDocumentTypes::route('/'),
        ];
    }
}
