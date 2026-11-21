@extends('account.layout', ['accountTitle' => 'Замовлення '.$order->displayNumber()])

@section('account-content')
<a class="shop-btn shop-secondary mb-3" href="{{ route('maker.orders') }}">← До замовлень на мої вироби</a>
<section class="shop-panel account-orders" aria-labelledby="maker-order-items">
    <h2 id="maker-order-items">Ваші вироби</h2>
    <p>{{ $order->created_at->timezone('Europe/Kyiv')->format('d.m.Y H:i') }} · {{ $status }} · {{ $order->paymentStatusLabel() }}</p>
    <table>
        <thead>
            <tr><th scope="col">Виріб</th><th scope="col">Кількість</th><th scope="col">Сума майстра</th></tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <th scope="row">{{ $item->name }} @if ($item->made_to_order)<small>Під замовлення</small>@endif</th>
                    <td>{{ $item->quantity }} шт.</td>
                    <td>{{ $item->maker_price_minor !== null ? \App\Support\Money::display($item->makerAccruedMinor()) : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</section>
@endsection
