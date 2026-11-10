@extends('account.layout', ['accountTitle' => 'Особистий кабінет'])
@section('account-content')
<p>Вітаємо, {{ auth()->user()->name }}.</p>
@unless(auth()->user()->hasVerifiedEmail())
<div class="shop-alert account-verify"><p>Підтвердьте електронну пошту, щоб додавати попередні гостьові замовлення.</p><form method="post" action="{{ route('verification.send') }}">@csrf<button class="shop-btn shop-secondary" type="submit">Надіслати лист</button></form></div>
@endunless
<h2>Мої замовлення</h2>
@if($orders->isEmpty())
<div class="shop-panel shop-empty"><p>У кабінеті ще немає замовлень. Покупки, оформлені після входу, з’являтимуться тут автоматично.</p><a href="{{ route('shop.index') }}" class="shop-btn">До крамниці</a></div>
@else
<div class="shop-panel account-orders"><table><thead><tr><th>Замовлення</th><th>Дата</th><th>Оплата</th><th>Сума</th><th></th></tr></thead><tbody>
@foreach($orders as $order)<tr><td><span class="account-order-ref">{{ Str::limit($order->reference, 13, '') }}</span></td><td>{{ $order->created_at->timezone('Europe/Kyiv')->format('d.m.Y') }}</td><td>{{ \App\Models\Order::paymentLabels()[$order->payment_status] ?? $order->payment_status }}</td><td>{{ \App\Support\Money::display($order->total_minor) }}</td><td><a href="{{ route('account.order', $order->reference) }}">Переглянути →</a></td></tr>@endforeach
</tbody></table></div>
@if($orders->hasPages()){{ $orders->links('partials.articles-pagination') }}@endif
@endif
<p class="account-help">Оформлювали замовлення без входу? Відкрийте його особисте посилання з листа, увійдіть у кабінет із тією самою підтвердженою поштою та натисніть «Додати до кабінету».</p>
@endsection
