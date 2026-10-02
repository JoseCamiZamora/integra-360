<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Platform\Resources\Companies;

use App\Filament\Tables\Columns\StatusBadgeColumn;
use App\Support\Status\StatusTone;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Core\Filament\Platform\Resources\Companies\Pages\CreateCompany;
use Modules\Core\Filament\Platform\Resources\Companies\Pages\EditCompany;
use Modules\Core\Filament\Platform\Resources\Companies\Pages\ListCompanies;
use Modules\Core\Filament\Platform\Resources\Companies\RelationManagers\LicensesRelationManager;
use Modules\Core\Filament\Schemas\CompanyForm;
use Modules\Core\Models\Company;

/**
 * Platform panel: client companies. Created with their main branch;
 * deactivated, never deleted.
 */
final class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'legal_name';

    public static function getModelLabel(): string
    {
        return __('core::companies.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::companies.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('core::companies.navigation');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components(CompanyForm::components(forPlatform: true));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('legal_name')
            ->columns([
                TextColumn::make('legal_name')
                    ->label(__('core::companies.fields.legal_name'))
                    ->description(fn (Company $record): ?string => $record->trade_name)
                    ->searchable(['legal_name', 'trade_name'])
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('nit')
                    ->label(__('core::companies.fields.nit'))
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('city')
                    ->label(__('core::companies.fields.city'))
                    ->toggleable(),
                StatusBadgeColumn::make('status')
                    ->label(__('core::users.fields.status'))
                    ->status(fn (Company $record): StatusTone => $record->is_active ? StatusTone::Success : StatusTone::Neutral)
                    ->statusLabel(fn (Company $record): string => $record->is_active
                        ? __('core::companies.status.active')
                        : __('core::companies.status.inactive')),
                TextColumn::make('created_at')
                    ->label(__('core::companies.fields.created_at'))
                    ->date()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('core::companies.fields.is_active')),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('toggleActive')
                    ->label(fn (Company $record): string => $record->is_active
                        ? __('core::companies.actions.deactivate')
                        : __('core::companies.actions.activate'))
                    ->icon(fn (Company $record): Heroicon => $record->is_active ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedCheckCircle)
                    ->color(fn (Company $record): string => $record->is_active ? 'danger' : 'primary')
                    ->authorize('update')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Company $record): ?string => $record->is_active ? __('core::companies.help.is_active') : null)
                    ->action(function (Company $record): void {
                        $record->update(['is_active' => ! $record->is_active]);

                        Notification::make()->success()->title($record->is_active
                            ? __('core::companies.notifications.activated')
                            : __('core::companies.notifications.deactivated'))->send();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            LicensesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'create' => CreateCompany::route('/create'),
            'edit' => EditCompany::route('/{record}/edit'),
        ];
    }
}
