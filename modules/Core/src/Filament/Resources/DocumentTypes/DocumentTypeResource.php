<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\DocumentTypes;

use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\Resources\DocumentTypes\Pages\ManageDocumentTypes;
use Modules\Core\Filament\Support\DocumentTypeForm;
use Modules\Core\Models\DocumentType;
use Modules\Core\Providers\CoreServiceProvider;
use UnitEnum;

/**
 * Document types seen by the company: the global ones (read-only here,
 * managed in /plataforma) and its own, which only its administrator
 * manages (DocumentTypePolicy). Never deleted: deactivated.
 */
final class DocumentTypeResource extends Resource
{
    protected static ?string $model = DocumentType::class;

    protected static ?string $slug = 'document-types';

    // Global types have no company: BelongsToCompanyOrGlobal does the
    // scoping (globals + own), not Filament's tenant scope.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = CoreServiceProvider::OPERATION_GROUP;

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('core::document_types.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::document_types.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('core::document_types.navigation');
    }

    public static function form(Schema $schema): Schema
    {
        return DocumentTypeForm::configure($schema, fn () => DocumentType::query());
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
            'index' => ManageDocumentTypes::route('/'),
        ];
    }
}
