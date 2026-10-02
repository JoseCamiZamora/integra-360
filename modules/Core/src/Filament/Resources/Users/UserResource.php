<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Users;

use App\Filament\Tables\Columns\StatusBadgeColumn;
use App\Support\Status\StatusTone;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use Modules\Core\Actions\ResetUserPassword;
use Modules\Core\Actions\SetMembershipStatus;
use Modules\Core\Actions\UpdateCompanyUser;
use Modules\Core\Enums\CompanyRole;
use Modules\Core\Enums\DocumentType;
use Modules\Core\Filament\Resources\Users\Pages\ManageUsers;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Membership;
use Modules\Core\Models\User;
use Modules\Core\Support\TemporaryCredentials;
use UnitEnum;

/**
 * Users of the active company. Users are not company-scoped (one account may
 * serve several companies): this resource lists the users with a membership
 * in the active company (Membership is company-scoped), and UserPolicy checks
 * that membership again for every record.
 */
final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    // Isolation comes from getEloquentQuery() (memberships of the active
    // company); Filament's own tenant scope expects a BelongsTo relation.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'core';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('core::users.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::users.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('core::users.navigation');
    }

    /**
     * @return Builder<User>
     */
    public static function getEloquentQuery(): Builder
    {
        return User::query()
            ->inActiveCompany()
            ->addSelect([
                'membership_active' => Membership::query()->select('is_active')->whereColumn('user_id', 'users.id')->limit(1),
                'branch_name' => Branch::query()->select('branches.name')
                    ->join('company_user', 'company_user.branch_id', '=', 'branches.id')
                    ->whereColumn('company_user.user_id', 'users.id')
                    ->limit(1),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->components([
                ...self::identityFields(),
                Select::make('branch_id')
                    ->label(__('core::users.fields.branch'))
                    ->options(fn (): array => Branch::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->native(false),
                CheckboxList::make('roles')
                    ->label(__('core::users.fields.roles'))
                    ->helperText(__('core::users.help.roles'))
                    ->options(CompanyRole::options())
                    ->required()
                    ->minItems(1)
                    ->validationMessages(['required' => __('core::users.validation.roles_required')])
                    ->columns(['default' => 1, 'sm' => 2])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Identity and contact fields (also used by the platform panel to create
     * a company's first administrator).
     *
     * @return list<Component>
     */
    public static function identityFields(): array
    {
        return [
            TextInput::make('name')
                ->label(__('core::users.fields.name'))
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Select::make('document_type')
                ->label(__('core::users.fields.document_type'))
                ->options(DocumentType::options())
                ->default(DocumentType::CC->value)
                ->required()
                ->native(false)
                ->live(),
            TextInput::make('document_number')
                ->label(__('core::users.fields.document_number'))
                ->required()
                ->maxLength(20)
                // "1.020.304.050" is stored and checked as "1020304050".
                ->stripCharacters(['.', ' ', '-'])
                ->regex('/^[A-Za-z0-9]+$/')
                ->extraInputAttributes(['class' => 'font-mono'])
                ->unique(
                    table: 'users',
                    column: 'document_number',
                    ignorable: fn (?Model $record): ?User => $record instanceof User ? $record : null,
                    modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('document_type', $get('document_type')),
                )
                ->validationMessages(['unique' => __('core::users.validation.document_taken')]),
            TextInput::make('email')
                ->label(__('core::users.fields.email'))
                ->helperText(__('core::users.help.email'))
                ->email()
                ->maxLength(255)
                ->unique(table: 'users', column: 'email', ignorable: fn (?Model $record): ?User => $record instanceof User ? $record : null),
            TextInput::make('phone')
                ->label(__('core::users.fields.phone'))
                ->tel()
                ->maxLength(20),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('core::users.fields.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('document_number')
                    ->label(__('core::users.fields.document'))
                    ->formatStateUsing(fn (User $record): string => $record->document_type->value.' '.$record->document_number)
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('roles_list')
                    ->label(__('core::users.fields.roles'))
                    ->state(fn (User $record): string => $record->forgetCompanyRoles()->getRoleNames()
                        ->map(fn (string $role): string => CompanyRole::labelFor($role))
                        ->implode(', '))
                    ->wrap(),
                TextColumn::make('branch_name')
                    ->label(__('core::users.fields.branch'))
                    ->toggleable(),
                TextColumn::make('email')
                    ->label(__('core::users.fields.email'))
                    ->toggleable(isToggledHiddenByDefault: true),
                StatusBadgeColumn::make('status')
                    ->label(__('core::users.fields.status'))
                    ->status(fn (User $record): StatusTone => match (true) {
                        ! $record->getAttribute('membership_active') => StatusTone::Neutral,
                        $record->must_change_password => StatusTone::Warning,
                        default => StatusTone::Success,
                    })
                    ->statusLabel(fn (User $record): string => match (true) {
                        ! $record->getAttribute('membership_active') => __('core::users.status.inactive'),
                        $record->must_change_password => __('core::users.status.must_change_password'),
                        default => __('core::users.status.active'),
                    }),
                TextColumn::make('last_login_at')
                    ->label(__('core::users.fields.last_login_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label(__('core::users.filters.status'))
                    ->trueLabel(__('core::users.status.active'))
                    ->falseLabel(__('core::users.status.inactive'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('memberships', fn (Builder $membership): Builder => $membership->where('is_active', true)),
                        false: fn (Builder $query): Builder => $query->whereHas('memberships', fn (Builder $membership): Builder => $membership->where('is_active', false)),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                SelectFilter::make('role')
                    ->label(__('core::users.filters.role'))
                    ->options(CompanyRole::options())
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->whereHas('roles', fn (Builder $roles): Builder => $roles->where('name', $data['value']))),
            ])
            ->recordActions([
                EditAction::make()
                    ->fillForm(fn (User $record): array => [
                        ...$record->only(['name', 'document_number', 'email', 'phone']),
                        'document_type' => $record->document_type->value,
                        'branch_id' => Membership::query()->where('user_id', $record->getKey())->value('branch_id'),
                        'roles' => $record->forgetCompanyRoles()->getRoleNames()->all(),
                    ])
                    ->using(fn (User $record, array $data, UpdateCompanyUser $update): User => $update->handle(
                        $record,
                        $data,
                        array_values($data['roles']),
                    )),
                Action::make('resetPassword')
                    ->label(__('core::users.actions.reset_password'))
                    ->icon(Heroicon::OutlinedKey)
                    ->authorize('resetPassword')
                    ->requiresConfirmation()
                    ->modalDescription(__('core::users.confirm.reset_password'))
                    ->action(function (User $record, ResetUserPassword $reset): void {
                        /** @var User $administrator */
                        $administrator = auth()->user();

                        self::notifyTemporaryPassword($reset->handle($record, $administrator));
                    }),
                Action::make('deactivate')
                    ->label(__('core::users.actions.deactivate'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->authorize('deactivate')
                    ->visible(fn (User $record): bool => (bool) $record->getAttribute('membership_active'))
                    ->requiresConfirmation()
                    ->modalDescription(__('core::users.confirm.deactivate'))
                    ->action(function (User $record, SetMembershipStatus $status): void {
                        $status->handle($record, false);

                        Notification::make()->success()->title(__('core::users.notifications.deactivated'))->send();
                    }),
                Action::make('activate')
                    ->label(__('core::users.actions.activate'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->authorize('deactivate')
                    ->visible(fn (User $record): bool => ! $record->getAttribute('membership_active'))
                    ->action(function (User $record, SetMembershipStatus $status): void {
                        $status->handle($record, true);

                        Notification::make()->success()->title(__('core::users.notifications.activated'))->send();
                    }),
            ]);
    }

    /**
     * Shown once: the password is not stored in clear text anywhere.
     */
    public static function notifyTemporaryPassword(TemporaryCredentials $credentials): void
    {
        Notification::make()
            ->success()
            ->persistent()
            ->title(__('core::users.notifications.temporary_password_title', ['name' => $credentials->user->name]))
            ->body(__('core::users.notifications.temporary_password_body', [
                'password' => $credentials->password,
                'document' => $credentials->user->document_number,
            ]))
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
