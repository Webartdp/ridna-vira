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
        .shop-product-crumbs,.shop-product-maker{
            font-family:var(--rv-font-body);font-size:.875rem;line-height:1.5;
            color:#665642;overflow-wrap:anywhere;
        }
        .shop-product-crumbs{margin-bottom:20px}
        .shop-product-crumbs ol{display:flex;flex-wrap:wrap;gap:4px 8px;list-style:none;padding:0;margin:0}
        .shop-product-crumbs li{display:flex;align-items:baseline;gap:8px;min-width:0;max-width:100%}
        .shop-product-crumbs li > span[aria-hidden]{color:#927750}
        .shop-product-crumbs a,.shop-product-maker a{text-decoration:underline;text-underline-offset:.18em}
        .shop-product-maker{margin:0 0 14px}
    </style>
@endpush
@section('content')
<section class="shop">
    <nav class="shop-product-crumbs" aria-label="Хлібні крихти">
        <ol>
            @foreach ($product->breadcrumbTrail() as $crumb)
                <li>
                    @unless ($loop->first)<span aria-hidden="true">›</span>@endunless
                    @if ($loop->last)
                        <span aria-current="page">{{ $crumb['name'] }}</span>
                    @else
                        <a href="{{ $crumb['path'] }}">{{ $crumb['name'] }}</a>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
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
