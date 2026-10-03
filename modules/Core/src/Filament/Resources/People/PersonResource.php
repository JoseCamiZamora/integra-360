<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\People;

use App\Filament\Tables\Columns\StatusBadgeColumn;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Actions\CreatePersonAccess;
use Modules\Core\Actions\DeletePerson;
use Modules\Core\Actions\RestorePerson;
use Modules\Core\Actions\SetPersonStatus;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Enums\ComplianceStatus;
use Modules\Core\Enums\IdentityDocumentType;
use Modules\Core\Enums\LicenseCategory;
use Modules\Core\Enums\PersonStatus;
use Modules\Core\Filament\Resources\People\Pages\CreatePerson;
use Modules\Core\Filament\Resources\People\Pages\EditPerson;
use Modules\Core\Filament\Resources\People\Pages\ListPeople;
use Modules\Core\Filament\Resources\Shared\DocumentsRelationManager;
use Modules\Core\Filament\Resources\Users\UserResource;
use Modules\Core\Filament\Support\ActionErrors;
use Modules\Core\Filament\Support\ComplianceBadges;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Person;
use Modules\Core\Providers\CoreServiceProvider;
use UnitEnum;

/**
 * People of the active company, with their driver profile, documents and
 * system access. Authorized by PersonPolicy; data is validated by the
 * actions (PersonInput).
 */
final class PersonResource extends Resource
{
    protected static ?string $model = Person::class;

    protected static ?string $slug = 'people';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = CoreServiceProvider::OPERATION_GROUP;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'first_name';

    public static function getModelLabel(): string
    {
        return __('core::people.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::people.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('core::people.navigation');
    }

    /**
     * @return Builder<Person>
     */
    public static function getEloquentQuery(): Builder
    {
        return Person::query()->with('driver');
    }

    /**
     * Driver fields live in the same form; PersonPolicy::manageDriverProfile
     * decides whether they can be changed.
     */
    public static function form(Schema $schema): Schema
    {
        $canManageDriver = fn (?Person $record): bool => $record === null
            ? (auth()->user()?->checkPermissionTo('core.drivers.update') ?? false)
            : (auth()->user()?->can('manageDriverProfile', $record) ?? false);

        return $schema
            ->columns(1)
            ->components([
                Section::make(__('core::people.sections.identification'))
                    ->columns(['default' => 1, 'sm' => 2])
                    ->components([
                        Select::make('document_type')
                            ->label(__('core::people.fields.document_type'))
                            ->options(IdentityDocumentType::options())
                            ->default(IdentityDocumentType::CC->value)
                            ->required()
                            ->native(false),
                        TextInput::make('document_number')
                            ->label(__('core::people.fields.document_number'))
                            ->required()
                            ->maxLength(20)
                            ->inputMode('numeric')
                            ->extraInputAttributes(['class' => 'font-mono']),
                        TextInput::make('first_name')
                            ->label(__('core::people.fields.first_name'))
                            ->required()
                            ->maxLength(100),
                        TextInput::make('last_name')
                            ->label(__('core::people.fields.last_name'))
                            ->required()
                            ->maxLength(100),
                        DatePicker::make('birth_date')
                            ->label(__('core::people.fields.birth_date'))
                            ->native(false)
                            ->maxDate(now()),
                    ]),
                Section::make(__('core::people.sections.contact_and_work'))
                    ->columns(['default' => 1, 'sm' => 2])
                    ->components([
                        TextInput::make('phone')
                            ->label(__('core::people.fields.phone'))
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('email')
                            ->label(__('core::people.fields.email'))
                            ->helperText(__('core::people.help.email'))
                            ->email()
                            ->maxLength(255),
                        TextInput::make('position')
                            ->label(__('core::people.fields.position'))
                            ->required()
                            ->maxLength(100),
                        TextInput::make('area')
                            ->label(__('core::people.fields.area'))
                            ->maxLength(100),
                        Select::make('branch_id')
                            ->label(__('core::people.fields.branch_id'))
                            ->options(fn (): array => Branch::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->native(false),
                        DatePicker::make('hired_at')
                            ->label(__('core::people.fields.hired_at'))
                            ->native(false),
                    ]),
                Section::make(__('core::people.sections.driver'))
                    ->description(__('core::people.help.driver'))
                    ->columns(['default' => 1, 'sm' => 2])
                    ->components([
                        Toggle::make('is_driver')
                            ->label(__('core::people.fields.is_driver'))
                            ->live()
                            ->disabled(fn (?Person $record): bool => ! $canManageDriver($record))
                            ->columnSpanFull(),
                        TextInput::make('license_number')
                            ->label(__('core::people.fields.license_number'))
                            ->required(fn (Get $get): bool => (bool) $get('is_driver'))
                            ->visible(fn (Get $get): bool => (bool) $get('is_driver'))
                            ->disabled(fn (?Person $record): bool => ! $canManageDriver($record))
                            ->maxLength(30)
                            ->extraInputAttributes(['class' => 'font-mono']),
                        Select::make('license_category')
                            ->label(__('core::people.fields.license_category'))
                            ->options(LicenseCategory::options())
                            ->required(fn (Get $get): bool => (bool) $get('is_driver'))
                            ->visible(fn (Get $get): bool => (bool) $get('is_driver'))
                            ->disabled(fn (?Person $record): bool => ! $canManageDriver($record))
                            ->native(false),
                        TextInput::make('experience_years')
                            ->label(__('core::people.fields.experience_years'))
                            ->visible(fn (Get $get): bool => (bool) $get('is_driver'))
                            ->disabled(fn (?Person $record): bool => ! $canManageDriver($record))
                            ->integer()
                            ->minValue(0)
                            ->maxValue(60),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('first_name')
            ->columns([
                TextColumn::make('full_name')
                    ->label(__('core::people.fields.name'))
                    ->weight('medium')
                    ->description(fn (Person $record): string => $record->position)
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name']),
                TextColumn::make('document_number')
                    ->label(__('core::people.fields.document_number'))
                    ->formatStateUsing(fn (Person $record): string => $record->document_type->value.' '.$record->document_number)
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('driver.license_category')
                    ->label(__('core::people.fields.driver'))
                    ->formatStateUsing(fn (LicenseCategory $state): string => __('core::people.driver_with_category', ['category' => $state->value]))
                    ->placeholder('—'),
                StatusBadgeColumn::make('status')
                    ->label(__('core::people.fields.status'))
                    ->status(fn (Person $record): PersonStatus => $record->status),
                StatusBadgeColumn::make('compliance')
                    ->label(__('core::people.fields.compliance'))
                    ->status(fn (Person $record): ComplianceStatus => app(ComplianceBadges::class)->person($record)->status),
                TextColumn::make('user_id')
                    ->label(__('core::people.fields.access'))
                    ->formatStateUsing(fn (?int $state): string => $state === null ? __('core::people.access.none') : __('core::people.access.yes'))
                    ->placeholder(__('core::people.access.none'))
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('core::people.fields.status'))
                    ->options(PersonStatus::options())
                    ->default(PersonStatus::Active->value),
                TernaryFilter::make('drivers')
                    ->label(__('core::people.filters.drivers'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('driver'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('driver'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                self::createAccessAction(),
                self::changeStatusAction(),
                DeleteAction::make()
                    ->modalDescription(__('core::people.help.delete'))
                    ->using(function (Person $record, DeletePerson $delete): bool {
                        $delete->handle($record);

                        return true;
                    }),
                RestoreAction::make()
                    ->using(function (Person $record, RestorePerson $restore): bool {
                        ActionErrors::inModal(fn (): Person => $restore->handle($record));

                        return true;
                    }),
            ]);
    }

    /**
     * "Crear acceso al sistema": the temporary password is shown once.
     */
    public static function createAccessAction(): Action
    {
        return Action::make('createAccess')
            ->label(__('core::people.actions.create_access'))
            ->icon(Heroicon::OutlinedKey)
            ->authorize('createAccess')
            ->modalDescription(fn (Person $record): string => $record->isDriver()
                ? __('core::people.confirm.create_access_driver')
                : __('core::people.confirm.create_access'))
            ->schema(fn (Person $record): array => $record->isDriver() ? [] : [
                CheckboxList::make('roles')
                    ->label(__('core::users.fields.roles'))
                    ->options(array_diff_key(CompanyRole::options(), [CompanyRole::Driver->value => true]))
                    ->required()
                    ->columns(['default' => 1, 'sm' => 2]),
            ])
            ->action(function (Person $record, array $data, CreatePersonAccess $create): void {
                $credentials = ActionErrors::inModal(fn () => $create->handle($record, array_values($data['roles'] ?? [])));

                UserResource::notifyTemporaryPassword($credentials);
            });
    }

    /**
     * Retire or reactivate (with a user account, their membership follows).
     */
    public static function changeStatusAction(): Action
    {
        return Action::make('changeStatus')
            ->label(fn (Person $record): string => $record->isActive() ? __('core::people.actions.retire') : __('core::people.actions.reactivate'))
            ->icon(fn (Person $record): Heroicon => $record->isActive() ? Heroicon::OutlinedUserMinus : Heroicon::OutlinedUserPlus)
            ->color(fn (Person $record): string => $record->isActive() ? 'danger' : 'gray')
            ->authorize('changeStatus')
            ->hidden(fn (Person $record): bool => $record->trashed())
            ->requiresConfirmation()
            ->modalDescription(fn (Person $record): string => $record->isActive()
                ? __('core::people.confirm.retire')
                : __('core::people.confirm.reactivate'))
            ->action(function (Person $record, SetPersonStatus $setStatus): void {
                $status = $record->isActive() ? PersonStatus::Inactive : PersonStatus::Active;

                ActionErrors::inModal(fn (): Person => $setStatus->handle($record, $status));

                Notification::make()->success()->title($status === PersonStatus::Active
                    ? __('core::people.notifications.reactivated')
                    : __('core::people.notifications.retired'))->send();
            });
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPeople::route('/'),
            'create' => CreatePerson::route('/create'),
            'edit' => EditPerson::route('/{record}/edit'),
        ];
    }
}
