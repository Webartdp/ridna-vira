@extends('layouts.app')
@section('title', $product->name.' — Крамниця Рідної Віри')
@push('styles')
    @include('shop.styles')
    <style>
        .shop .shop-product-title{
            font-size:clamp(1.5rem,2.8vw,2.25rem);
            line-height:1.2;
            margin:0 0 12px;
            overflow-wrap:anywhere;
        }
        .shop-product-maker{
            margin:0 0 14px;
            font-family:var(--rv-font-body);font-size:.875rem;line-height:1.5;
            color:#665642;overflow-wrap:anywhere;
        }
        .shop-product-maker a{text-decoration:underline;text-underline-offset:.18em}
    </style>
@endpush
@section('content')
<section class="shop">
    @include('shop.breadcrumbs', ['trail' => $product->breadcrumbTrail()])
    <div class="shop-split shop-product-layout">
        @include('shop.product-gallery')
        <article>
            <h1 class="shop-product-title">{{ $product->name }}</h1>
            @if ($product->maker)
                <p class="shop-product-maker">Майстер · <a href="{{ route('shop.index', ['maker' => $product->maker->id]) }}">{{ $product->maker->name }}</a></p>
            @endif
            <p class="shop-price">{{ \App\Support\Money::display($product->price_minor) }}</p>
            @if($product->sku)<small>Артикул: {{ $product->sku }}</small>@endif
            <p>{{ $product->availabilityLabel() }}</p>
            <form method="post" action="{{ route('shop.cart.update', $product) }}" class="inline">@csrf<input type="hidden" name="add" value="1"><label for="qty">Кількість</label><input id="qty" type="number" name="quantity" value="1" min="1" max="{{ $product->purchaseLimit() }}" required><button class="shop-btn" @disabled(!$product->purchaseLimit())>До кошика</button></form>
            @if($errors->any())<p class="shop-error">{{ $errors->first() }}</p>@endif
            <div class="prose" style="margin-top:32px">{!! \App\Support\CmsHtml::clean($product->description ?? '') !!}</div>
        </article>
    </div>
</section>
@endsection
