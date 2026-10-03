<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Documents;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Actions\RegisterExpiringDocument;
use Modules\Core\Contracts\Documentable;
use Modules\Core\Enums\DocumentAppliesTo;
use Modules\Core\Enums\PersonStatus;
use Modules\Core\Filament\Resources\Documents\Pages\ListExpiringDocuments;
use Modules\Core\Filament\Support\ActionErrors;
use Modules\Core\Filament\Support\DocumentFields;
use Modules\Core\Filament\Support\DocumentTable;
use Modules\Core\Models\DocumentType;
use Modules\Core\Models\ExpiringDocument;
use Modules\Core\Models\Person;
use Modules\Core\Models\Vehicle;
use Modules\Core\Providers\CoreServiceProvider;
use UnitEnum;

/**
 * "Documentos": every document of the company, of people and vehicles, with
 * filters by status, type, holder and the next 30 days. The daily screen
 * of whoever watches SOAT, technical inspections and licenses.
 */
final class ExpiringDocumentResource extends Resource
{
    protected static ?string $model = ExpiringDocument::class;

    protected static ?string $slug = 'documents';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = CoreServiceProvider::OPERATION_GROUP;

    protected static ?int $navigationSort = 30;

    public static function getModelLabel(): string
    {
        return __('core::documents.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::documents.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('core::documents.navigation');
    }

    /**
     * @return Builder<ExpiringDocument>
     */
    public static function getEloquentQuery(): Builder
    {
        return DocumentTable::query(ExpiringDocument::query())->with('documentable');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query): Builder => DocumentTable::defaultSort($query))
            ->columns([
                TextColumn::make('holder')
                    ->label(__('core::documents.fields.holder'))
                    ->state(fn (ExpiringDocument $record): string => $record->documentable instanceof Documentable ? $record->documentable->documentLabel() : '—')
                    ->description(fn (ExpiringDocument $record): string => DocumentAppliesTo::tryFrom($record->documentable_type)?->label() ?? '')
                    ->weight('medium'),
                ...DocumentTable::columns(),
            ])
            ->filters([
                ...DocumentTable::filters(),
                SelectFilter::make('documentable_type')
                    ->label(__('core::documents.filters.holder_kind'))
                    ->options(DocumentAppliesTo::options()),
            ])
            ->headerActions([
                self::registerAction(),
            ])
            ->recordActions(DocumentTable::recordActions())
            ->emptyStateHeading(__('core::documents.empty'));
    }

    /**
     * Registering from the general list: first whose document it is.
     */
    private static function registerAction(): Action
    {
        $holder = fn (Get $get): ?Model => match ($get('holder_kind')) {
            DocumentAppliesTo::Vehicle->value => Vehicle::query()->find($get('holder_id')),
            DocumentAppliesTo::Person->value => Person::query()->find($get('holder_id')),
            default => null,
        };

        return Action::make('register')
            ->label(__('core::documents.actions.register'))
            ->icon(Heroicon::OutlinedPlus)
            ->authorize(fn (): bool => auth()->user()?->can('create', ExpiringDocument::class) ?? false)
            ->modalWidth('2xl')
            ->schema([
                Select::make('holder_kind')
                    ->label(__('core::documents.fields.holder_kind'))
                    ->options(DocumentAppliesTo::options())
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('holder_id', null);
                        $set('document_type_id', null);
                    }),
                Select::make('holder_id')
                    ->label(__('core::documents.fields.holder'))
                    ->options(fn (Get $get): array => match ($get('holder_kind')) {
                        DocumentAppliesTo::Vehicle->value => Vehicle::query()->inService()->orderBy('plate')->pluck('plate', 'id')->all(),
                        DocumentAppliesTo::Person->value => Person::query()->where('status', PersonStatus::Active->value)->orderBy('first_name')->get()
                            ->mapWithKeys(fn (Person $person): array => [$person->getKey() => $person->full_name])->all(),
                        default => [],
                    })
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('document_type_id', null)),
                Select::make('document_type_id')
                    ->label(__('core::documents.fields.document_type'))
                    ->options(function (Get $get) use ($holder): array {
                        $entity = $holder($get);

                        return $entity instanceof Documentable
                            ? DocumentType::query()->applicableTo($entity)->orderBy('name')->pluck('name', 'id')->all()
                            : [];
                    })
                    ->required()
                    ->native(false)
                    ->live(),
                ...DocumentFields::data(),
                DocumentFields::files(),
            ])
            ->action(function (array $data, RegisterExpiringDocument $register): void {
                $entity = match ($data['holder_kind']) {
                    DocumentAppliesTo::Vehicle->value => Vehicle::query()->findOrFail($data['holder_id']),
                    default => Person::query()->findOrFail($data['holder_id']),
                };
                $type = DocumentType::query()->findOrFail($data['document_type_id']);

                unset($data['holder_kind'], $data['holder_id']);

                ActionErrors::inModal(fn () => $register->handle($entity, $type, DocumentFields::withoutFiles($data), DocumentFields::uploaded($data)));

                Notification::make()->success()->title(__('core::documents.notifications.registered'))->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpiringDocuments::route('/'),
        ];
    }
}
