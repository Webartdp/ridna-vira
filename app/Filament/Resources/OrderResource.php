<?php

namespace App\Filament\Resources;

use App\Models\Order;
use App\Models\Product;
use App\Services\Checkout;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $model = Order::class;

    protected static ?string $modelLabel = 'замовлення';

    protected static ?string $pluralModelLabel = 'Замовлення';

    protected static string|\UnitEnum|null $navigationGroup = 'Крамниця';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return auth()->user()?->canManage('admin') ? $query->withTrashed() : $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('order_number')->label('Номер замовлення')
                ->formatStateUsing(fn (?Order $record) => $record?->displayNumber())
                ->disabled()->columnSpanFull(),
            TextInput::make('customer_name')->label('Покупець')->required()->maxLength(150),
            TextInput::make('phone')->label('Телефон')->tel()->required()->maxLength(30)->regex('/^\+?[\d ()-]{9,30}$/'),
            TextInput::make('email')->label('Пошта')->email()->required()->maxLength(190),
            TextInput::make('payment_method')->label('Спосіб оплати')->formatStateUsing(fn ($state) => Order::paymentMethods()[$state] ?? $state)->disabled(),
            TextInput::make('payment_status')->label('Оплата')->formatStateUsing(fn ($state) => Order::paymentLabels()[$state] ?? $state)->disabled(),
            TextInput::make('delivery_method')->label('Спосіб доставки')
                ->formatStateUsing(fn ($state) => $state === 'nova_poshta' ? 'Нова пошта' : $state)->disabled(),
            Textarea::make('delivery_address')->label('Адреса доставки або відділення')->required()->maxLength(1000),
            Textarea::make('comment')->label('Коментар покупця')->maxLength(2000)->columnSpanFull(),
            View::make('filament.order-items')->columnSpanFull()
                ->hidden(fn (?Order $record): bool => $record?->canEditItems() ?? false),
            TextInput::make('order_total')->label('Поточна сума')->disabled()
                ->formatStateUsing(fn (?Order $record) => $record ? Money::display($record->total_minor) : null)
                ->visible(fn (?Order $record): bool => $record?->canEditItems() ?? false),
            Repeater::make('items')->label('Склад замовлення')->columnSpanFull()
                ->visible(fn (?Order $record): bool => $record?->canEditItems() ?? false)
                ->addActionLabel('Додати позицію')->reorderable(false)->minItems(1)
                ->helperText('Ціна нової позиції береться з каталогу. Під час збереження буде перераховано суму замовлення й залишки.')
                ->columns(3)->schema([
                    Hidden::make('id'),
                    Hidden::make('original_name')->dehydrated(false),
                    Select::make('product_id')->label('Виріб')->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => Product::where('is_active', true)
                            ->whereLike('name', '%'.$search.'%')->orderBy('name')->limit(30)->get(['id', 'name', 'price_minor'])
                            ->mapWithKeys(fn (Product $product) => [$product->id => $product->name.' · '.Money::display($product->price_minor)])->all())
                        ->getOptionLabelUsing(fn ($value, Get $get): ?string => $get('original_name') ?? Product::find($value)?->name)
                        ->placeholder(fn (Get $get): string => $get('original_name') ?? 'Оберіть виріб')
                        ->required(fn (Get $get): bool => blank($get('id')))
                        ->disabled(fn (Get $get): bool => filled($get('id')))->dehydrated(),
                    TextInput::make('unit_price')->label('Ціна за одиницю')->disabled()
                        ->visible(fn (Get $get): bool => filled($get('id'))),
                    TextInput::make('quantity')->label('Кількість')->required()->integer()->minValue(1)->maxValue(99),
                ]),
            Select::make('fulfilment_status')->label('Виконання')->required()->options(['new' => 'Нове', 'processing' => 'Готується', 'shipped' => 'Відправлено', 'completed' => 'Виконано', 'cancelled' => 'Скасовано'])
                ->helperText('Оплату WayForPay підтверджує сервіс. Післяплату підтвердіть після фактичного надходження коштів.'),
            TextInput::make('tracking_number')->label('Номер відправлення')->maxLength(190),
            Textarea::make('admin_notes')->label('Внутрішні нотатки')->maxLength(10000)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('order_number')->label('Номер')
                ->state(fn (Order $record) => $record->displayNumber())
                ->searchable(query: fn (Builder $query, string $search) => $query->searchNumber($search))
                ->copyable(),
            TextColumn::make('customer_name')->label('Покупець')->searchable(), TextColumn::make('phone')->label('Телефон')->searchable(),
            TextColumn::make('total_minor')->label('Сума')->formatStateUsing(fn ($state) => Money::display((int) $state))->sortable(),
            TextColumn::make('payment_method')->label('Спосіб оплати')->formatStateUsing(fn ($state) => Order::paymentMethods()[$state] ?? $state),
            TextColumn::make('payment_status')->label('Оплата')->formatStateUsing(fn (Order $record) => $record->paymentStatusLabel())->badge()->color(fn ($state) => match ($state) {
                'paid' => 'success', 'review' => 'danger', default => 'warning'
            }),
            TextColumn::make('fulfilment_status')->label('Виконання')->formatStateUsing(fn ($state) => ['new' => 'Нове', 'processing' => 'Готується', 'shipped' => 'Відправлено', 'completed' => 'Виконано', 'cancelled' => 'Скасовано'][$state] ?? $state)->badge(),
            TextColumn::make('created_at')->label('Створено')->dateTime('d.m.Y H:i')->sortable(),
        ])->filters([
            SelectFilter::make('payment_status')->label('Оплата')->options(Order::paymentLabels()),
            TrashedFilter::make()->label('Видалені замовлення')
                ->visible(fn (): bool => auth()->user()?->canManage('admin') ?? false),
        ])
            ->recordActions([
                EditAction::make()->label('Редагувати')->visible(fn (Order $record): bool => ! $record->trashed()),
                Action::make('confirmCashOnDelivery')->label('Підтвердити отримання й оплату')
                    ->authorize(fn () => auth()->user()?->canManage('shop'))
                    ->visible(fn (Order $record) => ! $record->trashed() && $record->isCashOnDelivery() && $record->payment_status === 'pending'
                        && $record->fulfilment_status === 'shipped' && ! $record->stock_released_at)
                    ->requiresConfirmation()
                    ->modalDescription('Підтвердіть лише після вручення посилки та фактичного надходження післяплати.')
                    ->action(fn (Order $record) => app(Checkout::class)->confirmCashOnDelivery($record)),
                Action::make('cancel')->label('Скасувати й повернути залишок')
                    ->authorize(fn () => auth()->user()?->canManage('shop'))
                    ->requiresConfirmation()
                    ->visible(fn (Order $record) => ! $record->trashed() && $record->hasUnpaidReservation())
                    ->action(fn (Order $record) => app(Checkout::class)->release($record, 'expired', cancel: true)),
                DeleteAction::make()->label('Видалити')
                    ->modalDescription('Замовлення зникне з основного списку. Його можна відновити через фільтр видалених замовлень.')
                    ->using(function (Order $record): bool {
                        app(Checkout::class)->archiveOrder($record);

                        return true;
                    }),
                RestoreAction::make()->label('Відновити'),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Order::where('fulfilment_status', 'new')->where(fn ($query) => $query
            ->whereIn('payment_status', ['paid', 'review'])
            ->orWhere(fn ($cash) => $cash->where('payment_method', 'cod')->where('payment_status', 'pending')))->count();
    }

    public static function getPages(): array
    {
        return [
            'index' => OrderResource\Pages\ManageOrders::route('/'),
            'edit' => OrderResource\Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
