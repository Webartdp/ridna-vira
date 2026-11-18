@extends('layouts.app')
@section('title', 'Кошик — Рідна Віра')
@push('styles')
    @include('shop.styles')
    <style>
        .checkout-layout{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:24px;margin-top:30px;align-items:start}
        .checkout-fields{display:grid;gap:16px;min-width:0}
        .checkout-layout h2{font-size:1.5rem;margin:0 0 14px}
        .checkout-payment-method{padding:12px 14px;background:#f4ecdc;border:1px solid #e2d4bb;border-radius:8px}
        .checkout-payment-method small{margin:2px 0 8px}.checkout-payment-method label{margin:0}
        .shop .checkout-payment-method input[type=radio]{width:auto;margin-right:6px;accent-color:#493a23}
        .checkout-totals{margin:18px 0 0}
        .checkout-totals>div{display:flex;justify-content:space-between;gap:12px;padding:8px 0}
        .checkout-totals dd{margin:0;text-align:right}
        .checkout-total{border-top:1px solid #d7cab3;font-weight:700}
        .checkout-submit{width:100%;margin-top:12px}
        #nova-poshta-lookup:not([hidden]){margin:14px 0;padding:12px;border:1px solid #d7cab3;border-radius:8px}
        #nova-poshta-lookup button{margin-top:8px}
        @media(max-width:760px){.checkout-layout{grid-template-columns:minmax(0,1fr);gap:16px}}
    </style>
@endpush
@section('content')
<section class="shop"><h1>Кошик</h1>
    @if(session('status'))<div class="shop-alert" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="shop-alert shop-error" role="alert"><ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @forelse($items as $item)
        <article class="shop-row"><div><a href="{{ route('shop.product', $item['product']) }}">{{ $item['product']->name }}</a><small>{{ \App\Support\Money::display($item['product']->price_minor) }} за одиницю</small>@if($item['product']->made_to_order)<small>Під замовлення</small>@endif @if($item['product']->purchaseLimit() < $item['quantity'])<small class="shop-error">Перевірте кількість: товар тимчасово недоступний або залишку недостатньо.</small>@endif</div>
        <form class="inline" method="post" action="{{ route('shop.cart.update', $item['product']) }}">@csrf<label class="visually-hidden" for="quantity-{{ $item['product']->id }}">Кількість {{ $item['product']->name }}</label><input id="quantity-{{ $item['product']->id }}" type="number" name="quantity" value="{{ $item['quantity'] }}" min="0" max="99"><button class="shop-btn shop-secondary">Оновити</button><button class="shop-btn shop-secondary" name="quantity" value="0" aria-label="Видалити {{ $item['product']->name }}">×</button></form>
        <strong>{{ \App\Support\Money::display($item['total']) }}</strong></article>
    @empty
        <div class="shop-panel shop-empty"><h2>Кошик поки порожній</h2><a class="shop-btn" href="{{ route('shop.index') }}">До крамниці</a></div>
    @endforelse
    @if($items)
    <form method="post" action="{{ route('shop.checkout') }}" class="checkout-layout">
        @csrf
        <div class="checkout-fields">
            <section class="shop-panel" aria-labelledby="checkout-contacts">
                <h2 id="checkout-contacts">Контактні дані</h2>
                <input type="hidden" name="checkout_token" value="{{ session('checkout_token') }}">
                @guest<p><a href="{{ route('login') }}">Увійдіть</a>, щоб замовлення збереглося у вашому кабінеті, або оформіть покупку як гість.</p>@endguest
                <label for="customer-name">Ім’я та прізвище</label><input id="customer-name" name="customer_name" value="{{ old('customer_name', auth()->user()?->name) }}" autocomplete="name" required maxlength="150">
                <label for="customer-email">Електронна пошта</label><input id="customer-email" type="email" name="email" value="{{ auth()->check() ? auth()->user()->email : old('email') }}" autocomplete="email" required maxlength="190" @readonly(auth()->check())>
                <label for="customer-phone">Телефон</label><input id="customer-phone" type="tel" name="phone" value="{{ old('phone', auth()->user()?->phone) }}" autocomplete="tel" required maxlength="30">
            </section>

            <section class="shop-panel" aria-labelledby="checkout-delivery">
                <h2 id="checkout-delivery">Доставка</h2>
                <input type="hidden" name="delivery_method" value="nova_poshta">
                <p><strong>Нова пошта</strong> · відділення або поштомат</p>
                @if ($novaPoshtaReady)
                    @include('shop.nova-poshta')
                @endif
                <label for="delivery-address">Місто та відділення або поштомат</label><textarea id="delivery-address" name="delivery_address" required maxlength="1000" rows="3">{{ old('delivery_address', auth()->user()?->delivery_address) }}</textarea>
                <small>Доставку оплачує покупець за тарифами перевізника при отриманні. </small>
                <label for="order-comment">Коментар (необов’язково)</label><textarea id="order-comment" name="comment" maxlength="2000" rows="2">{{ old('comment') }}</textarea>
            </section>
        </div>

        <section class="shop-panel checkout-summary" aria-labelledby="checkout-payment">
            <h2 id="checkout-payment">Оплата</h2>
            @php($selectedPayment = $paymentsReady ? old('payment_method', 'wayforpay') : 'cod')
            <fieldset class="checkout-payment-method">
                <legend class="visually-hidden">Спосіб оплати</legend>
                <label><input type="radio" name="payment_method" value="cod" required @checked($selectedPayment === 'cod')> При отриманні</label>
                <small>Післяплата Нової пошти за тарифами перевізника.</small>
                <label><input type="radio" name="payment_method" value="wayforpay" required @checked($selectedPayment === 'wayforpay') @disabled(!$paymentsReady)> Онлайн карткою через WayForPay</label>
                @if (!$paymentsReady)
                    <small>Онлайн-оплата тимчасово недоступна.</small>
                @endif
            </fieldset>
            <dl class="checkout-totals">
                <div><dt>Товари</dt><dd>{{ \App\Support\Money::display($total) }}</dd></div>
                <div><dt>Доставка</dt><dd>Окремо</dd></div>
                <div class="checkout-total"><dt>До оплати за товари</dt><dd>{{ \App\Support\Money::display($total) }}</dd></div>
            </dl>
            <label><input type="checkbox" name="consent" value="1" required @checked(old('consent'))> Погоджуюся з <a href="{{ url('/umovy-kramnytsi') }}" target="_blank" rel="noopener">умовами замовлення та обробкою даних</a>.</label>
            @if (!$ordersEnabled)
                <div class="shop-alert" role="status">Оформлення замовлень тимчасово недоступне.</div>
            @endif
            <button class="shop-btn checkout-submit" type="submit" @disabled(!$ordersEnabled)>Оформити замовлення</button>
        </section>
    </form>
    @endif
</section>
@endsection
