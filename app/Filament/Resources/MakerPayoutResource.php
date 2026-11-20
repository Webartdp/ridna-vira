<?php

namespace App\Filament\Resources;

use App\Models\OrderItem;
use App\Services\MakerPayouts;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MakerPayoutResource extends Resource
{
    protected static ?string $model = OrderItem::class;

    protected static ?string $slug = 'maker-payouts';

    protected static ?string $pluralModelLabel = 'Виплати майстрам';

    protected static string|\UnitEnum|null $navigationGroup = 'Крамниця';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManage('admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotNull('maker_price_minor')
            ->whereHas('order', fn (Builder $query) => $query->whereNotNull('paid_at'))
            ->with(['order', 'maker']);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('maker.name')->label('Майстер')->description(fn (OrderItem $record) => $record->maker?->email)->searchable(),
            TextColumn::make('order_number')->label('Замовлення')
                ->state(fn (OrderItem $record) => $record->order->displayNumber())
                ->searchable(query: fn (Builder $query, string $search) => $query
                    ->whereHas('order', fn (Builder $orders) => $orders->searchNumber($search)))
                ->copyable(),
            TextColumn::make('name')->label('Виріб')->description(fn (OrderItem $record) => $record->quantity.' шт.')->searchable()->wrap(),
            TextColumn::make('maker_price_minor')->label('Нараховано')->formatStateUsing(fn ($state, OrderItem $record) => Money::display($record->makerAccruedMinor())),
            TextColumn::make('maker_paid_minor')->label('Фактично виплачено')->formatStateUsing(fn ($state) => Money::display((int) $state))->placeholder('—'),
            TextColumn::make('payout_status')->label('Статус')->state(fn (OrderItem $record) => $record->makerPayoutStatus())->badge()
                ->color(fn (OrderItem $record) => match ($record->makerPayoutStatus()) {
                    'Виплачено' => 'success', 'До виплати' => 'warning', 'Повернення після виплати' => 'danger', default => 'gray',
                }),
            TextColumn::make('maker_paid_at')->label('Дата виплати')->dateTime('d.m.Y H:i')->placeholder('—'),
            TextColumn::make('maker_payout_reference')->label('Номер переказу')->copyable()->placeholder('—'),
        ])->filters([
            SelectFilter::make('maker_id')->label('Майстер')->relationship('maker', 'name')->searchable(),
            Filter::make('ready')->label('Лише до виплати')->query(fn (Builder $query) => $query
                ->whereNotNull('maker_id')->whereNull('maker_paid_at')
                ->whereHas('order', fn (Builder $orders) => $orders->where('payment_status', 'paid')->where('fulfilment_status', 'completed'))),
        ])->recordActions([
            Action::make('recordPayout')->label('Зафіксувати виплату')
                ->visible(fn (OrderItem $record) => $record->canRecordMakerPayout())
                ->modalDescription('Перед переказом звірте повернення в WayForPay. Внесіть дані після фактичного переказу майстру.')
                ->schema(fn (OrderItem $record) => [
                    TextInput::make('amount')->label('Фактично виплачено, грн')->required()
                        ->default(Money::decimal($record->makerAccruedMinor()))->maxLength(10)
                        ->regex('/^\d{1,7}(?:[.,]\d{1,2})?$/D'),
                    TextInput::make('reference')->label('Номер операції')->required()->maxLength(190),
                ])->action(fn (OrderItem $record, array $data) => app(MakerPayouts::class)
                ->record(auth()->user(), $record, $data['amount'], $data['reference']))
                ->successNotificationTitle('Виплату зафіксовано'),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => MakerPayoutResource\Pages\ListMakerPayouts::route('/')];
    }
}
