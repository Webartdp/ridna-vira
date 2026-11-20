@extends('layouts.app')
@section('title', 'Кошик — Рідна Віра')
@push('styles')
    @include('shop.styles')
    <style>
        .checkout-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(300px,360px);gap:24px;margin-top:30px;align-items:start}
        .checkout-summary{position:sticky;top:88px}
        .cart-product{display:grid;grid-template-columns:80px minmax(0,1fr) auto;gap:14px;align-items:center;padding:16px 0;border-top:1px solid #e2d4bb}
        .cart-product:last-child{padding-bottom:0}
        .cart-product-photo{display:grid;place-items:center;width:80px;height:80px;background:#f4ecdc;border:1px solid #e2d4bb;border-radius:9px;overflow:hidden}
        .cart-product-photo img{display:block;width:100%;height:100%;object-fit:contain}
        .cart-product-copy{min-width:0;overflow-wrap:anywhere}
        .cart-product-copy small{margin-top:4px}
        .cart-product-actions{display:grid;grid-template-columns:auto auto;gap:6px;align-items:center;justify-content:end}
        .cart-quantity-status{grid-column:1/-1;max-width:190px;color:#743d30}
        .cart-quantity-status:empty{display:none}
        .cart-product-total{grid-column:1/-1;text-align:right;white-space:nowrap}
        .cart-quantity{display:flex;align-items:center;gap:4px}
        .shop .cart-quantity input[type=number]{width:64px;padding:6px}
        .cart-quantity .shop-btn{padding:8px}
        .cart-remove{display:grid;place-items:center;width:36px;height:36px;border:1px solid #e2d4bb;border-radius:6px;background:transparent;color:#743d30;cursor:pointer}
        .cart-remove:hover{background:#f4ecdc}
        .cart-remove-icon{width:19px;height:19px}
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
        @media(max-width:760px){.checkout-layout{grid-template-columns:minmax(0,1fr);gap:16px}.checkout-summary{position:static}}
        @media(max-width:960px){.cart-product-actions{grid-column:2;justify-content:start}.cart-product-total{text-align:left}}
        @media(max-width:420px){.cart-product{grid-template-columns:60px minmax(0,1fr);gap:10px}.cart-product-photo{width:60px;height:60px}.cart-quantity .shop-btn{font-size:.85rem}}
    </style>
@endpush
@section('content')
<section class="shop"><h1>Кошик</h1>
    @if(session('status'))<div class="shop-alert" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="shop-alert shop-error" role="alert"><ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if ($items)
    <div class="checkout-layout">
        <div class="checkout-fields">
            <section class="shop-panel cart-products" aria-labelledby="cart-products-title">
                <h2 id="cart-products-title">Товари в кошику</h2>
                @foreach ($items as $item)
                    @php($product = $item['product'])
                    <article class="cart-product">
                        <a class="cart-product-photo" href="{{ route('shop.product', $product) }}" aria-label="{{ $product->name }}">
                            <img src="{{ !empty($product->images[0]) ? $product->imageUrl($product->images[0], 400) : asset('assets/brand/image-placeholder.svg') }}"
                                width="80" height="80" alt="" loading="lazy" decoding="async">
                        </a>
                        <div class="cart-product-copy">
                            <strong><a href="{{ route('shop.product', $product) }}">{{ $product->name }}</a></strong>
                            <small><span data-unit-price>{{ \App\Support\Money::display($product->price_minor) }}</span> за одиницю</small>
                            @if ($product->made_to_order)<small>Під замовлення</small>@endif
                            @if ($product->purchaseLimit() < $item['quantity'])
                                <small class="shop-error" data-stock-warning>Перевірте кількість. Товар тимчасово недоступний або залишку недостатньо.</small>
                            @endif
                        </div>
                        <div class="cart-product-actions">
                            <strong class="cart-product-total" data-line-total>{{ \App\Support\Money::display($item['total']) }}</strong>
                            <form class="cart-quantity" method="post" action="{{ route('shop.cart.update', $product) }}">
                                @csrf
                                <label class="visually-hidden" for="quantity-{{ $product->id }}">Кількість {{ $product->name }}</label>
                                <input id="quantity-{{ $product->id }}" type="number" name="quantity" value="{{ $item['quantity'] }}" min="1" max="99" required>
                                <button class="shop-btn shop-secondary" type="submit">Оновити</button>
                            </form>
                            <form data-cart-remove method="post" action="{{ route('shop.cart.update', $product) }}">
                                @csrf
                                <input type="hidden" name="quantity" value="0">
                                <button class="cart-remove" type="submit" aria-label="Видалити {{ $product->name }}" title="Видалити">
                                    @svg('heroicon-o-trash', 'cart-remove-icon')
                                </button>
                            </form>
                            <small class="cart-quantity-status" data-quantity-status role="status"></small>
                        </div>
                    </article>
                @endforeach
            </section>

            <form method="post" id="checkout-form" action="{{ route('shop.checkout') }}" class="checkout-fields">
                @csrf
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
                        <input id="delivery-address" type="hidden" name="delivery_address">
                        <noscript><p>Увімкніть JavaScript, щоб вибрати відділення Нової пошти.</p></noscript>
                    @else
                        <p class="shop-alert" role="status">Вибір відділення Нової пошти тимчасово недоступний.</p>
                    @endif
                    <label for="order-comment">Коментар (необов’язково)</label><textarea id="order-comment" name="comment" maxlength="2000" rows="2">{{ old('comment') }}</textarea>
                </section>
            </form>
        </div>

        <section class="shop-panel checkout-summary" aria-labelledby="checkout-payment">
            <h2 id="checkout-payment">Оплата</h2>
            @php($selectedPayment = $paymentsReady ? old('payment_method', 'wayforpay') : 'cod')
            <fieldset class="checkout-payment-method">
                <legend class="visually-hidden">Спосіб оплати</legend>
                <label><input type="radio" form="checkout-form" name="payment_method" value="cod" required @checked($selectedPayment === 'cod')> При отриманні</label>
                <small>Післяплата Нової пошти за тарифами перевізника.</small>
                <label><input type="radio" form="checkout-form" name="payment_method" value="wayforpay" required @checked($selectedPayment === 'wayforpay') @disabled(!$paymentsReady)> Онлайн карткою через WayForPay</label>
                @if (!$paymentsReady)
                    <small>Онлайн-оплата тимчасово недоступна.</small>
                @endif
            </fieldset>
            <dl class="checkout-totals">
                <div><dt>Товари</dt><dd data-cart-total>{{ \App\Support\Money::display($total) }}</dd></div>
                <div><dt>Доставка</dt><dd>Окремо, за тарифами Нової пошти</dd></div>
                <div class="checkout-total"><dt>До оплати за товари</dt><dd data-cart-total>{{ \App\Support\Money::display($total) }}</dd></div>
            </dl>
            <label><input type="checkbox" form="checkout-form" name="consent" value="1" required @checked(old('consent'))> Погоджуюся з <a href="{{ url('/umovy-kramnytsi') }}" target="_blank" rel="noopener">умовами замовлення та обробкою даних</a>.</label>
            @include('partials.shop-policy-links', [
                'links' => \App\Support\ShopPolicies::buyerLinks(),
                'label' => 'Умови крамниці для покупців',
                'newTab' => true,
            ])
            @if (!$ordersEnabled)
                <div class="shop-alert" role="status">Оформлення замовлень тимчасово недоступне.</div>
            @endif
            <button class="shop-btn checkout-submit" form="checkout-form" type="submit" @disabled(!$ordersEnabled || !$novaPoshtaReady)>Оформити замовлення</button>
        </section>
    </div>
    @include('shop.cart-quantity')
    @else
        <div class="shop-panel shop-empty"><h2>Кошик поки порожній</h2><a class="shop-btn" href="{{ route('shop.index') }}">До крамниці</a></div>
    @endif
</section>
@endsection
