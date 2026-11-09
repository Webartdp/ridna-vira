<h1>Дякуємо за замовлення!</h1>
<p>{{ $order->customer_name }}, оплату підтверджено.</p>
<p>Замовлення: {{ $order->reference }}</p>
<ul>@foreach($order->items as $item)<li>{{ $item->name }} · {{ $item->quantity }} шт.</li>@endforeach</ul>
<p>Разом: {{ \App\Support\Money::display($order->total_minor) }}</p>
<p>Доставка: {{ $order->delivery_address }}</p>
<p><a href="{{ $order->publicUrl() }}">Переглянути замовлення</a></p>
<p>Духовний центр «Рідна Віра»</p>
