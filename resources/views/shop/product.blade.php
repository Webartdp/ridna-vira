@extends('layouts.app')
@section('title', $product->name.' — Крамниця Рідної Віри')
@push('styles') @include('shop.styles') @endpush
@section('content')
<section class="shop">
    <p><a href="{{ route('shop.index') }}">← До крамниці</a></p>
    <div class="shop-split">
        <div class="shop-gallery">@forelse($product->images ?? [] as $image)<img src="{{ $product->imageUrl($image) }}" srcset="{{ $product->imageSrcset($image) }}" sizes="(min-width: 900px) 600px, 90vw" alt="{{ $product->name }}" width="800" height="800" @if(!$loop->first) loading="lazy" @endif decoding="async">@empty<img src="{{ asset('assets/brand/image-placeholder.svg') }}" width="800" height="800" alt="" decoding="async">@endforelse</div>
        <article><h1>{{ $product->name }}</h1><p class="shop-price">{{ \App\Support\Money::display($product->price_minor) }}</p>
            @if($product->sku)<small>Артикул: {{ $product->sku }}</small>@endif
            <p>{{ $product->stock ? 'Є в наявності' : 'Немає в наявності' }}</p>
            <form method="post" action="{{ route('shop.cart.update', $product) }}" class="inline">@csrf<input type="hidden" name="add" value="1"><label for="qty">Кількість</label><input id="qty" type="number" name="quantity" value="1" min="1" max="{{ min(99, $product->stock) }}" required><button class="shop-btn" @disabled(!$product->stock)>До кошика</button></form>
            @if($errors->any())<p class="shop-error">{{ $errors->first() }}</p>@endif
            <div class="prose" style="margin-top:32px">{!! \App\Support\CmsHtml::clean($product->description ?? '') !!}</div>
        </article>
    </div>
</section>
@endsection
