<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use App\Filament\Tables\Columns\StatusBadgeColumn;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Actions\RenewExpiringDocument;
use Modules\Core\Actions\UpdateExpiringDocument;
use Modules\Core\Enums\DocumentStatus;
use Modules\Core\Models\DocumentType;
use Modules\Core\Models\ExpiringDocument;
use Modules\Core\Models\ExpiringDocumentFile;
use Modules\Core\Providers\Filament\AdminPanelProvider;

/**
 * Columns, filters and record actions of document lists: the documents tab
 * of people and vehicles and the general "Documentos" screen. Every action
 * is authorized by ExpiringDocumentPolicy (hidden in read-only mode).
 */
final class DocumentTable
{
    /**
     * @return list<Column>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('documentType.name')
                ->label(__('core::documents.fields.document_type'))
                ->weight('medium')
                ->wrap(),
            TextColumn::make('number')
                ->label(__('core::documents.fields.number'))
                ->fontFamily('mono')
                ->placeholder('—')
                ->toggleable(),
            TextColumn::make('expires_at')
                ->label(__('core::documents.fields.expires_at'))
                ->date()
                ->placeholder(__('core::documents.no_expiry'))
                ->sortable(),
            StatusBadgeColumn::make('status')
                ->label(__('core::documents.fields.status'))
                ->status(fn (ExpiringDocument $record): DocumentStatus => $record->status())
                ->statusLabel(fn (ExpiringDocument $record): ?string => $record->is_current ? null : __('core::documents.status.replaced')),
            TextColumn::make('files_count')
                ->label(__('core::documents.fields.files'))
                ->counts('files')
                ->alignCenter()
                ->toggleable(),
        ];
    }

    /**
     * Filters on dates (the status is calculated, never stored): "expired"
     * is before today, "expiring soon" within the type's warning days.
     *
     * @return list<BaseFilter>
     */
    public static function filters(): array
    {
        $today = fn (): string => DocumentStatus::today()->toDateString();

        return [
            SelectFilter::make('status')
                ->label(__('core::documents.fields.status'))
                ->options(DocumentStatus::options())
                ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                    DocumentStatus::Expired->value => $query->whereDate('expires_at', '<', $today()),
                    DocumentStatus::ExpiringSoon->value => $query->whereDate('expires_at', '>=', $today())
                        ->whereHas('documentType', fn (Builder $type): Builder => $type
                            ->whereRaw('expiring_documents.expires_at <= date_add(?, interval document_types.warning_days day)', [$today()])),
                    DocumentStatus::Valid->value => $query
                        ->whereHas('documentType', fn (Builder $type): Builder => $type
                            ->whereRaw('expiring_documents.expires_at > date_add(?, interval document_types.warning_days day)', [$today()])),
                    DocumentStatus::NoExpiry->value => $query->whereNull('expires_at'),
                    default => $query,
                }),
            SelectFilter::make('document_type_id')
                ->label(__('core::documents.fields.document_type'))
                ->options(fn (): array => DocumentType::query()->orderBy('name')->pluck('name', 'id')->all()),
            Filter::make('next_30_days')
                ->label(__('core::documents.filters.next_30_days'))
                ->toggle()
                ->query(fn (Builder $query): Builder => $query
                    ->whereDate('expires_at', '>=', $today())
                    ->whereDate('expires_at', '<=', DocumentStatus::today()->addDays(30)->toDateString())),
            TernaryFilter::make('is_current')
                ->label(__('core::documents.filters.current'))
                ->trueLabel(__('core::documents.filters.only_current'))
                ->falseLabel(__('core::documents.filters.only_replaced'))
                ->default(true),
            TrashedFilter::make(),
        ];
    }

    /**
     * @return list<Action>
     */
    public static function recordActions(): array
    {
        return [
            Action::make('files')
                ->label(__('core::documents.actions.files'))
                ->icon(Heroicon::OutlinedPaperClip)
                ->color('gray')
                ->visible(fn (ExpiringDocument $record): bool => ($record->files_count ?? $record->files()->count()) > 0)
                ->modalHeading(fn (ExpiringDocument $record): string => $record->documentType->name)
                ->modalContent(fn (ExpiringDocument $record) => view('core::filament.document-files', [
                    'files' => $record->files()->get()->map(fn (ExpiringDocumentFile $file): array => [
                        'name' => $file->original_name,
                        'size' => number_format($file->size / 1024, 0, ',', '.').' KB',
                        'url' => auth()->user()?->can('view', $file)
                            ? route('filament.admin.'.AdminPanelProvider::DOCUMENT_FILE_ROUTE, ['tenant' => Filament::getTenant(), 'file' => $file])
                            : null,
                    ])->all(),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('core::documents.actions.close')),
            Action::make('renew')
                ->label(__('core::documents.actions.renew'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->authorize('renew')
                ->modalHeading(fn (ExpiringDocument $record): string => __('core::documents.actions.renew_heading', ['type' => $record->documentType->name]))
                ->modalDescription(__('core::documents.help.renew'))
                ->schema(fn (ExpiringDocument $record): array => [
                    ...DocumentFields::data($record->documentType),
                    DocumentFields::files(),
                ])
                ->modalWidth('2xl')
                ->action(function (ExpiringDocument $record, array $data, RenewExpiringDocument $renew): void {
                    ActionErrors::inModal(fn () => $renew->handle($record, DocumentFields::withoutFiles($data), DocumentFields::uploaded($data)));

                    Notification::make()->success()->title(__('core::documents.notifications.renewed'))->send();
                }),
            EditAction::make()
                ->label(__('core::documents.actions.correct'))
                ->modalHeading(__('core::documents.actions.correct_heading'))
                ->modalDescription(__('core::documents.help.correct'))
                ->schema(fn (ExpiringDocument $record): array => DocumentFields::data($record->documentType))
                ->using(fn (ExpiringDocument $record, array $data, UpdateExpiringDocument $update): ExpiringDocument => ActionErrors::inModal(fn (): ExpiringDocument => $update->handle($record, $data))),
            DeleteAction::make()
                ->modalDescription(__('core::documents.help.delete')),
            RestoreAction::make(),
        ];
    }

    /**
     * Default order: the soonest expiry first, documents without expiry last
     * (MySQL would put NULLs first).
     *
     * @param  Builder<ExpiringDocument>  $query
     * @return Builder<ExpiringDocument>
     */
    public static function defaultSort(Builder $query): Builder
    {
        return $query->orderByRaw('expires_at is null')->orderBy('expires_at');
    }

    /**
     * Type, files and their counts in one go, without trashed-scope surprises.
     *
     * @param  Builder<ExpiringDocument>  $query
     * @return Builder<ExpiringDocument>
     */
    public static function query(Builder $query): Builder
    {
        return $query->with('documentType')->withCount('files');
    }
}
