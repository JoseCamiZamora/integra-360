<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Shared;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Actions\RegisterExpiringDocument;
use Modules\Core\Contracts\Documentable;
use Modules\Core\Filament\Support\ActionErrors;
use Modules\Core\Filament\Support\DocumentFields;
use Modules\Core\Filament\Support\DocumentTable;
use Modules\Core\Models\DocumentType;
use Modules\Core\Models\ExpiringDocument;

/**
 * "Documentos" tab of a person or a vehicle: current documents (and, with
 * the filter, the renewed ones), registering, renewing, correcting and
 * downloading files. Authorized by ExpiringDocumentPolicy.
 */
final class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('core::documents.plural');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('viewAny', ExpiringDocument::class) ?? false;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => DocumentTable::query($query))
            ->defaultSort('expires_at')
            ->columns(DocumentTable::columns())
            ->filters(DocumentTable::filters())
            ->headerActions([
                $this->registerAction(),
            ])
            ->recordActions(DocumentTable::recordActions())
            ->emptyStateHeading(__('core::documents.empty'));
    }

    private function registerAction(): Action
    {
        /** @var Documentable&Model $owner */
        $owner = $this->getOwnerRecord();

        return Action::make('register')
            ->label(__('core::documents.actions.register'))
            ->icon(Heroicon::OutlinedPlus)
            ->authorize(fn (): bool => auth()->user()?->can('create', ExpiringDocument::class) ?? false)
            ->modalWidth('2xl')
            ->schema([
                Select::make('document_type_id')
                    ->label(__('core::documents.fields.document_type'))
                    ->options(fn (): array => DocumentType::query()->applicableTo($owner)->orderBy('name')->pluck('name', 'id')->all())
                    ->required()
                    ->native(false)
                    ->live(),
                ...DocumentFields::data(),
                DocumentFields::files(),
            ])
            ->action(function (array $data, RegisterExpiringDocument $register) use ($owner): void {
                $type = DocumentType::query()->findOrFail($data['document_type_id']);

                ActionErrors::inModal(fn () => $register->handle($owner, $type, DocumentFields::withoutFiles($data), DocumentFields::uploaded($data)));

                Notification::make()->success()->title(__('core::documents.notifications.registered'))->send();
            });
    }
}
