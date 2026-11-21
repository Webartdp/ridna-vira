@extends('account.layout', ['accountTitle' => 'Замовлення на мої вироби'])

@section('account-content')
<section class="shop-panel account-verify mb-3" aria-labelledby="maker-telegram-title">
    <div>
        <h2 id="maker-telegram-title">Сповіщення в Telegram</h2>
        @if ($telegramConnected)
            <p>Telegram підключено.</p>
            @unless ($telegramConfigured)
                <p>Сповіщення тимчасово недоступні.</p>
            @endunless
        @elseif (!$telegramConfigured)
            <p>Підключення Telegram поки недоступне.</p>
        @elseif ($telegramPending)
            <p>Натисніть «Почати» в Telegram і оновіть сторінку за хвилину.</p>
        @else
            <p>Підключіть бота й натисніть «Почати» в Telegram, щоб отримувати нові замовлення.</p>
        @endif
    </div>
    <div class="account-button-row">
        @if (!$telegramConnected && $telegramConfigured)
            <form method="post" action="{{ route('maker.telegram.connect') }}">
                @csrf
                <button class="shop-btn" type="submit">{{ $telegramPending ? 'Відкрити Telegram знову' : 'Підключити Telegram' }}</button>
            </form>
        @endif
        @if ($telegramConnected || $telegramPending)
            <form method="post" action="{{ route('maker.telegram.disconnect') }}">
                @csrf
                @method('DELETE')
                <button class="shop-btn shop-secondary" type="submit">{{ $telegramConnected ? 'Від’єднати Telegram' : 'Скасувати підключення' }}</button>
            </form>
        @endif
    </div>
</section>

@if ($orders->isEmpty())
    <div class="shop-panel shop-empty"><p>Тут з’являться замовлення на ваші вироби.</p></div>
@else
    <div class="shop-panel account-orders" role="region" aria-label="Замовлення на мої вироби" tabindex="0">
        <table>
            <thead>
                <tr><th scope="col">Замовлення</th><th scope="col">Кількість</th><th scope="col">Сума майстра</th><th scope="col">Статус</th></tr>
            </thead>
            <tbody>
                @foreach ($orders as $order)
                    @php($pricedItems = $order->items->whereNotNull('maker_price_minor'))
                    <tr>
                        <th scope="row">
                            <a class="account-order-ref" href="{{ route('maker.order', $order->reference) }}">{{ $order->displayNumber() }}</a>
                            <small>{{ $order->created_at->timezone('Europe/Kyiv')->format('d.m.Y') }}</small>
                        </th>
                        <td>{{ $order->items->sum('quantity') }} шт.</td>
                        <td>{{ $pricedItems->isNotEmpty() ? \App\Support\Money::display($pricedItems->sum(fn ($item) => $item->makerAccruedMinor())) : '—' }}</td>
                        <td>{{ $statuses[$order->fulfilment_status] ?? 'Уточнюється' }}<small>{{ $order->paymentStatusLabel() }}</small></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($orders->hasPages())
        {{ $orders->links('partials.articles-pagination') }}
    @endif
@endif
@endsection
