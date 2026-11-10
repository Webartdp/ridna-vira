@extends('layouts.app')
@section('title', 'Кошик — Рідна Віра')
@push('styles') @include('shop.styles') @endpush
@section('content')
<section class="shop"><h1>Кошик</h1>
    @if(session('status'))<div class="shop-alert" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="shop-alert shop-error" role="alert"><ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @forelse($items as $item)
        <article class="shop-row"><div><a href="{{ route('shop.product', $item['product']) }}">{{ $item['product']->name }}</a><small>{{ \App\Support\Money::display($item['product']->price_minor) }} за одиницю</small>@if(!$item['product']->is_active || $item['product']->stock < $item['quantity'])<small class="shop-error">Перевірте кількість: товар тимчасово недоступний або залишку недостатньо.</small>@endif</div>
        <form class="inline" method="post" action="{{ route('shop.cart.update', $item['product']) }}">@csrf<label class="visually-hidden" for="quantity-{{ $item['product']->id }}">Кількість {{ $item['product']->name }}</label><input id="quantity-{{ $item['product']->id }}" type="number" name="quantity" value="{{ $item['quantity'] }}" min="0" max="99"><button class="shop-btn shop-secondary">Оновити</button><button class="shop-btn shop-secondary" name="quantity" value="0" aria-label="Видалити {{ $item['product']->name }}">×</button></form>
        <strong>{{ \App\Support\Money::display($item['total']) }}</strong></article>
    @empty
        <div class="shop-panel shop-empty"><h2>Кошик поки порожній</h2><a class="shop-btn" href="{{ route('shop.index') }}">До крамниці</a></div>
    @endforelse
    @if($items)
    <p class="shop-price" style="text-align:right;margin:24px 0">Разом: {{ \App\Support\Money::display($total) }}</p>
    <div class="shop-split"><div><h2>Оформлення замовлення</h2><p>Оплата карткою через WayForPay. Доставку оплачує покупець за тарифами перевізника при отриманні.</p><p>Після оформлення товари резервуються на 30 хвилин.</p>@if(!$paymentsReady)<div class="shop-alert">Онлайн-оплата ще налаштовується. Оформлення замовлень стане доступним після відкриття крамниці.</div>@endif</div>
    <form method="post" action="{{ route('shop.checkout') }}" class="shop-panel">@csrf
        <input type="hidden" name="checkout_token" value="{{ session('checkout_token') }}">
        @guest<p><a href="{{ route('login') }}">Увійдіть</a>, щоб замовлення збереглося у вашому кабінеті, або оформіть покупку як гість.</p>@endguest
        <label for="customer-name">Ім’я та прізвище</label><input id="customer-name" name="customer_name" value="{{ old('customer_name', auth()->user()?->name) }}" autocomplete="name" required maxlength="150">
        <label for="customer-email">Електронна пошта</label><input id="customer-email" type="email" name="email" value="{{ auth()->check() ? auth()->user()->email : old('email') }}" autocomplete="email" required maxlength="190" @readonly(auth()->check())>
        <label for="customer-phone">Телефон</label><input id="customer-phone" type="tel" name="phone" value="{{ old('phone', auth()->user()?->phone) }}" autocomplete="tel" required maxlength="30">
        <label for="delivery-method">Доставка</label><select id="delivery-method" name="delivery_method"><option value="nova_poshta" @selected(old('delivery_method') === 'nova_poshta')>Нова пошта</option><option value="ukrposhta" @selected(old('delivery_method') === 'ukrposhta')>Укрпошта</option><option value="pickup" @selected(old('delivery_method') === 'pickup')>Самовивіз за узгодженням</option></select>
        <label for="delivery-address">Місто, відділення / поштомат або адреса</label><textarea id="delivery-address" name="delivery_address" required maxlength="1000" rows="3">{{ old('delivery_address', auth()->user()?->delivery_address) }}</textarea>
        <label for="order-comment">Коментар (необов’язково)</label><textarea id="order-comment" name="comment" maxlength="2000" rows="2">{{ old('comment') }}</textarea>
        <label><input type="checkbox" name="consent" value="1" required @checked(old('consent'))> Погоджуюся з <a href="{{ url('/umovy-kramnytsi') }}" target="_blank" rel="noopener">умовами замовлення та обробкою даних</a>.</label>
        <button class="shop-btn" style="margin-top:20px" @disabled(!$paymentsReady)>Оформити й перейти до оплати</button>
    </form></div>
    @endif
</section>
@endsection
