@extends('layouts.app')
@section('title', 'Ваше замовлення — Рідна Віра')
@push('styles') @include('shop.styles') @endpush
@section('content')
<section class="shop"><h1>Ваше замовлення</h1><p>Номер: {{ $order->reference }}</p><p class="shop-status">{{ \App\Models\Order::paymentLabels()[$order->payment_status] ?? 'Очікує перевірки' }}</p>
    <div class="shop-panel"><table><thead><tr><th>Товар</th><th>Кількість</th><th>Сума</th></tr></thead><tbody>@foreach($order->items as $item)<tr><td>{{ $item->name }}</td><td>{{ $item->quantity }}</td><td>{{ \App\Support\Money::display($item->price_minor * $item->quantity) }}</td></tr>@endforeach</tbody></table><p class="shop-price" style="margin-top:20px">Разом: {{ \App\Support\Money::display($order->total_minor) }}</p></div>
    @if($order->payment_status === 'pending' && $order->reserved_until->isFuture())<div class="shop-alert">Товари зарезервовано до {{ $order->reserved_until->timezone('Europe/Kyiv')->format('H:i d.m.Y') }}. Після оплати підтвердження може надійти з невеликою затримкою.</div><form method="post" action="{{ route('shop.pay', $order->access_token) }}">@csrf<button class="shop-btn">Оплатити карткою через WayForPay</button></form><a class="shop-btn shop-secondary" style="margin-top:12px" href="{{ $order->publicUrl() }}">Оновити статус</a>
    @elseif($order->payment_status === 'paid')<p>Дякуємо! Оплату підтверджено. Ми підготуємо ваше замовлення до відправлення.</p>
    @elseif($order->payment_status === 'review')<p>Платіж отримано після завершення резерву. Ми перевіримо наявність товарів і зв’яжемося з вами.</p>
    @elseif(!$order->paid_at && $order->reserved_until->isPast())<p>Термін резервування завершився. Ви можете створити нове замовлення.</p><a class="shop-btn" href="{{ route('shop.index') }}">До крамниці</a>@endif
    <h2 style="margin-top:36px">Доставка</h2><p>{{ $order->customer_name }}<br>{{ $order->delivery_address }}</p>@if($order->tracking_number)<p>Номер відправлення: {{ $order->tracking_number }}</p>@endif
    <p><a href="{{ route('contact') }}">Зв’язатися з нами</a></p>
</section>
@endsection
