<?php

namespace App\Filament\Resources;

use App\Models\User;
use App\Services\ManageSiteUsers;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Password;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'користувача';

    protected static ?string $pluralModelLabel = 'Користувачі та ролі';

    protected static string|\UnitEnum|null $navigationGroup = 'Доступ';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Ім’я')->required()->maxLength(150),
            TextInput::make('email')->label('Електронна пошта')->email()->required()->maxLength(190)->unique(ignoreRecord: true),
            Select::make('role')->label('Роль')->options(User::roles())->required()->default('user')->helperText('Редактор — матеріали. Менеджер — крамниця. Користувач — лише особистий кабінет.'),
            Toggle::make('is_active')->label('Обліковий запис активний')->default(true)->required(),
            TextInput::make('password')->label('Новий пароль')->password()->revealable()->autocomplete('new-password')->minLength(12)->maxLength(128)->rules([Password::min(12)->letters()->numbers()])->required(fn (?User $record) => ! $record)->dehydrated(fn ($state) => filled($state))->afterStateHydrated(fn ($component) => $component->state(null))->helperText('Під час редагування залиште порожнім, щоб зберегти пароль.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('name')->label('Ім’я')->searchable(),
            TextColumn::make('email')->label('Пошта')->searchable(),
            TextColumn::make('role')->label('Роль')->formatStateUsing(fn ($state) => User::roles()[$state] ?? $state)->badge(),
            IconColumn::make('is_active')->label('Активний')->boolean(),
            IconColumn::make('email_verified_at')->label('Пошту підтверджено')->boolean(),
            TextColumn::make('created_at')->label('Реєстрація')->dateTime('d.m.Y H:i'),
        ])->filters([SelectFilter::make('role')->label('Роль')->options(User::roles())])->recordActions([
            EditAction::make()->using(fn (User $record, array $data) => app(ManageSiteUsers::class)->save($record, $data)),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => UserResource\Pages\ManageUsers::route('/')];
    }
}
