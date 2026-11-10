@extends('layouts.app')
@section('title', 'Крамниця — Рідна Віра')
@push('styles') @include('shop.styles') @endpush
@section('content')
<section class="shop">
    <h1>Крамниця</h1>
    <p>Книги, речі та видання, що підтримують живу традицію.</p>
    <form method="get" class="shop-filters">
        <div><label for="shop-search">Пошук</label><input id="shop-search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Назва товару"></div>
        <div><label for="shop-category">Категорія</label><select id="shop-category" name="category"><option value="">Усі категорії</option>@foreach($categories as $category)<option value="{{ $category->slug }}" @selected(request('category') === $category->slug)>{{ $category->name }}</option>@endforeach</select></div>
        <button class="shop-btn">Знайти</button>
    </form>
    <div class="shop-grid">
    @forelse($products as $product)
        <article class="shop-card">
            <a href="{{ route('shop.product', $product) }}">@if($product->images)<img src="{{ $product->imageUrl($product->images[0], 400) }}" srcset="{{ $product->imageSrcset($product->images[0]) }}" sizes="(min-width: 900px) 380px, (min-width: 540px) 45vw, 90vw" alt="{{ $product->name }}" loading="lazy" decoding="async" width="400" height="400">@else<img src="{{ asset('assets/brand/image-placeholder.svg') }}" width="400" height="400" alt="" loading="lazy" decoding="async">@endif</a>
            <h2><a href="{{ route('shop.product', $product) }}">{{ $product->name }}</a></h2>
            <span class="shop-price">{{ \App\Support\Money::display($product->price_minor) }}</span>
            <small>{{ $product->stock ? 'Є в наявності' : 'Немає в наявності' }}</small>
            <a class="shop-btn" href="{{ route('shop.product', $product) }}">Переглянути</a>
        </article>
    @empty
        <div class="shop-panel shop-empty" style="grid-column:1/-1"><h2>{{ request()->hasAny(['q','category']) ? 'Товарів не знайдено' : 'Готуємо крамницю до відкриття' }}</h2><p>{{ request()->hasAny(['q','category']) ? 'Спробуйте змінити пошук.' : 'Незабаром тут з’являться наші книги та інші товари.' }}</p></div>
    @endforelse
    </div>
    {{ $products->links('partials.articles-pagination') }}
</section>
@endsection
