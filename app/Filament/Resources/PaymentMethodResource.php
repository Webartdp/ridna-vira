<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentMethodResource\Pages\ManagePaymentMethods;
use App\Models\PaymentMethod;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PaymentMethodResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'спосіб оплати';

    protected static ?string $pluralModelLabel = 'Способи оплати';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Крамниця';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Назва для покупця')->required()->maxLength(120),
            Select::make('driver')
                ->label('Тип оплати')
                ->options(PaymentMethod::drivers())
                ->disableOptionWhen(fn (string $value, ?PaymentMethod $record): bool => $record === null
                    && $value !== PaymentMethod::MANUAL
                    && PaymentMethod::where('code', $value)->exists())
                ->in(fn (Select $component): array => array_keys($component->getEnabledOptions()))
                ->validationMessages(['in' => 'Виберіть доступний тип оплати.'])
                ->required()
                ->disabled(fn (?PaymentMethod $record): bool => $record !== null)
                ->live(),
            Textarea::make('description')
                ->label('Короткий опис')
                ->helperText('Показується біля способу оплати в кошику.')
                ->maxLength(500)
                ->rows(2)
                ->columnSpanFull(),
            Textarea::make('instructions')
                ->label('Інструкція з оплати')
                ->helperText('Покупець побачить її на сторінці оформленого замовлення.')
                ->required(fn (Get $get, ?PaymentMethod $record): bool => ($record?->driver ?? $get('driver')) === PaymentMethod::MANUAL)
                ->maxLength(2000)
                ->rows(4)
                ->columnSpanFull(),
            Toggle::make('is_active')->label('Увімкнено')->default(true),
            TextInput::make('sort_order')->label('Порядок показу')->required()->integer()->minValue(0)->maxValue(9999)->default(100),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort_order')->columns([
            TextColumn::make('name')->label('Спосіб оплати')->searchable()->sortable(),
            TextColumn::make('driver')
                ->label('Тип оплати')
                ->formatStateUsing(fn (string $state): string => PaymentMethod::drivers()[$state] ?? $state)
                ->badge(),
            ToggleColumn::make('is_active')
                ->label('Увімкнено')
                ->disabled(fn (PaymentMethod $record): bool => ! static::canEdit($record)),
            TextColumn::make('sort_order')->label('Порядок')->sortable(),
        ])->recordActions([
            EditAction::make()->label('Редагувати'),
            DeleteAction::make()->label('Видалити')->modalDescription('Спосіб оплати зникне з кошика.'),
        ])->emptyStateHeading('Способів оплати ще немає');
    }

    public static function getPages(): array
    {
        return ['index' => ManagePaymentMethods::route('/')];
    }
}
