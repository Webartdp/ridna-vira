<?php

namespace App\Filament\Resources;

use App\Models\Order;
use App\Services\Checkout;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('order_number')->label('Номер замовлення')
                ->formatStateUsing(fn (?Order $record) => $record?->displayNumber())
                ->disabled()->columnSpanFull(),
            TextInput::make('customer_name')->label('Покупець')->disabled(), TextInput::make('phone')->label('Телефон')->disabled(),
            TextInput::make('email')->label('Пошта')->disabled(),
            TextInput::make('payment_method')->label('Спосіб оплати')->formatStateUsing(fn ($state) => Order::paymentMethods()[$state] ?? $state)->disabled(),
            TextInput::make('payment_status')->label('Оплата')->formatStateUsing(fn ($state) => Order::paymentLabels()[$state] ?? $state)->disabled(),
            TextInput::make('delivery_method')->label('Спосіб доставки')->disabled(), Textarea::make('delivery_address')->label('Адреса доставки')->disabled(),
            Textarea::make('comment')->label('Коментар покупця')->disabled()->columnSpanFull(),
            View::make('filament.order-items')->columnSpanFull(),
            Select::make('fulfilment_status')->label('Виконання')->required()->options(['new' => 'Нове', 'processing' => 'Готується', 'shipped' => 'Відправлено', 'completed' => 'Виконано', 'cancelled' => 'Скасовано'])
                ->rules([fn ($record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                    if ($value === 'cancelled' && $record->isCashOnDelivery() && $record->payment_status === 'pending') {
                        $fail('Скористайтеся дією «Скасувати й повернути залишок».');
                    }
                    $canShipCashOnDelivery = $value === 'shipped' && $record->isCashOnDelivery() && $record->payment_status === 'pending' && ! $record->stock_released_at;
                    if (in_array($value, ['shipped', 'completed'], true) && $record->payment_status !== 'paid' && ! $canShipCashOnDelivery) {
                        $fail('Відправляти можна лише замовлення з підтвердженою оплатою.');
                    }
                }])->helperText('Оплату WayForPay підтверджує сервіс. Післяплату підтвердіть після фактичного надходження коштів.'),
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
        ])->filters([SelectFilter::make('payment_status')->label('Оплата')->options(Order::paymentLabels())])
            ->recordActions([
                EditAction::make()->label('Відкрити')->modalWidth('6xl'),
                Action::make('confirmCashOnDelivery')->label('Підтвердити отримання й оплату')
                    ->authorize(fn () => auth()->user()?->canManage('shop'))
                    ->visible(fn (Order $record) => $record->isCashOnDelivery() && $record->payment_status === 'pending'
                        && $record->fulfilment_status === 'shipped' && ! $record->stock_released_at)
                    ->requiresConfirmation()
                    ->modalDescription('Підтвердіть лише після вручення посилки та фактичного надходження післяплати.')
                    ->action(fn (Order $record) => app(Checkout::class)->confirmCashOnDelivery($record)),
                Action::make('cancel')->label('Скасувати й повернути залишок')->authorize(fn () => auth()->user()?->canManage('shop'))->requiresConfirmation()->visible(fn (Order $record) => ! $record->paid_at && ! $record->stock_released_at)->action(function (Order $record) {
                    app(Checkout::class)->release($record, 'expired', cancel: true);
                }),
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
        return ['index' => OrderResource\Pages\ManageOrders::route('/')];
    }
}
