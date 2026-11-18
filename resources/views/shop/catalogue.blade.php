@extends('layouts.app')
@section('title', 'Крамниця — Рідна Віра')
@push('styles')
    @include('shop.styles')
    <style>
        .shop-catalogue{display:grid;grid-template-columns:clamp(13rem,21vw,16rem) minmax(0,1fr);gap:24px;align-items:start;margin-top:28px}
        .shop-filters{display:grid;gap:18px;padding:18px;min-width:0}
        .shop-filters h2,.shop-filters legend{margin:0;font-size:1.125rem;line-height:1.3}
        .shop-filters fieldset{min-width:0;padding:0;border:0}
        .shop-filters legend{float:none;margin-bottom:8px}
        .shop-filters label{margin:0 0 4px}
        .shop-price-range{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
        .shop-price-range input{min-width:0;padding-inline:8px}
        .shop-filters .shop-category-option{display:flex;align-items:baseline;gap:8px;padding:4px 0;margin:0;overflow-wrap:anywhere;cursor:pointer}
        .shop-filters .shop-category-option input{width:auto;flex:none;accent-color:#493a23}
        .shop-category-group summary{padding:6px 0;cursor:pointer;overflow-wrap:anywhere}
        .shop-category-children{margin:0 0 4px 7px;padding-left:10px;border-left:1px solid #d7cab3}
        .shop-filters-reset{text-align:center}
        .shop-catalogue .shop-grid{gap:16px}
        .shop-catalogue .shop-card{padding:16px;overflow-wrap:anywhere}
        .shop-catalogue .shop-card img{height:auto;aspect-ratio:1;max-height:260px}
        .shop-catalogue .shop-card .shop-btn{margin-top:auto;text-align:center;padding-inline:10px}
        @media(max-width:900px){.shop-catalogue{grid-template-columns:minmax(0,1fr)}}
    </style>
@endpush
@section('content')
<section class="shop">
    <h1>Крамниця</h1>
    <p>Книги, речі та видання, що підтримують живу традицію.</p>
    @guest
        <p><a href="{{ route('maker.register') }}">Пропонувати свої вироби</a></p>
    @elseif (in_array(auth()->user()->roleName(), ['user', 'maker'], true))
        <p><a href="{{ route('maker.index') }}">Кабінет майстра</a></p>
    @endguest
    @php
        $selectedCategory = request()->string('category', strict: true)->toString();
        $priceFrom = old('price_from', request('price_from'));
        $priceTo = old('price_to', request('price_to'));
        $hasFilters = request()->anyFilled(['q', 'category', 'price_from', 'price_to']);
    @endphp
    <div class="shop-catalogue">
        <form method="get" action="{{ route('shop.index') }}" class="shop-panel shop-filters" aria-labelledby="shop-filters-title">
            <h2 id="shop-filters-title">Фільтри</h2>
            <div>
                <label for="shop-search">Пошук</label>
                <input id="shop-search" name="q" value="{{ request()->string('q', strict: true) }}" maxlength="100" placeholder="Назва товару">
            </div>
            <fieldset>
                <legend>Ціна, ₴</legend>
                <div class="shop-price-range">
                    <div>
                        <label for="shop-price-from">Від</label>
                        <input id="shop-price-from" name="price_from" value="{{ is_scalar($priceFrom) ? $priceFrom : '' }}" inputmode="decimal" maxlength="10" pattern="[0-9]{1,7}([.,][0-9]{1,2})?" placeholder="0">
                    </div>
                    <div>
                        <label for="shop-price-to">До</label>
                        <input id="shop-price-to" name="price_to" value="{{ is_scalar($priceTo) ? $priceTo : '' }}" inputmode="decimal" maxlength="10" pattern="[0-9]{1,7}([.,][0-9]{1,2})?">
                    </div>
                </div>
                @error('price_from')<small class="shop-error">{{ $message }}</small>@enderror
                @error('price_to')<small class="shop-error">{{ $message }}</small>@enderror
            </fieldset>
            <fieldset>
                <legend>Категорії</legend>
                <label class="shop-category-option">
                    <input type="radio" name="category" value="" @checked($selectedCategory === '')> Усі категорії
                </label>
                @foreach ($categories as $category)
                    @if ($category->children->isNotEmpty())
                        <details class="shop-category-group" @if ($selectedCategory === $category->slug || $category->children->contains('slug', $selectedCategory))open @endif>
                            <summary>{{ $category->name }}</summary>
                            <div class="shop-category-children">
                                <label class="shop-category-option">
                                    <input type="radio" name="category" value="{{ $category->slug }}" @checked($selectedCategory === $category->slug)> Уся категорія
                                </label>
                                @foreach ($category->children as $child)
                                    <label class="shop-category-option">
                                        <input type="radio" name="category" value="{{ $child->slug }}" @checked($selectedCategory === $child->slug)> {{ $child->name }}
                                    </label>
                                @endforeach
                            </div>
                        </details>
                    @else
                        <label class="shop-category-option">
                            <input type="radio" name="category" value="{{ $category->slug }}" @checked($selectedCategory === $category->slug)> {{ $category->name }}
                        </label>
                    @endif
                @endforeach
            </fieldset>
            <button class="shop-btn" type="submit">Застосувати</button>
            @if ($hasFilters)
                <a class="shop-filters-reset" href="{{ route('shop.index') }}">Скинути фільтри</a>
            @endif
        </form>
        <div>
            <div class="shop-grid">
                @forelse($products as $product)
                    <article class="shop-card">
                        <a href="{{ route('shop.product', $product) }}">@if($product->images)<img src="{{ $product->imageUrl($product->images[0], 400) }}" srcset="{{ $product->imageSrcset($product->images[0]) }}" sizes="(min-width: 1240px) 260px, (min-width: 901px) 22vw, (min-width: 541px) 44vw, 85vw" alt="{{ $product->name }}" loading="lazy" decoding="async" width="400" height="400">@else<img src="{{ asset('assets/brand/image-placeholder.svg') }}" width="400" height="400" alt="" loading="lazy" decoding="async">@endif</a>
                        <h2><a href="{{ route('shop.product', $product) }}">{{ $product->name }}</a></h2>
                        <span class="shop-price">{{ \App\Support\Money::display($product->price_minor) }}</span>
                        <small>{{ $product->availabilityLabel() }}</small>
                        <a class="shop-btn" href="{{ route('shop.product', $product) }}">Переглянути</a>
                    </article>
                @empty
                    <div class="shop-panel shop-empty" style="grid-column:1/-1"><h2>{{ $hasFilters ? 'Товарів не знайдено' : 'Готуємо крамницю до відкриття' }}</h2><p>{{ $hasFilters ? 'Спробуйте змінити фільтри.' : 'Незабаром тут з’являться наші книги та інші товари.' }}</p></div>
                @endforelse
            </div>
            {{ $products->links('partials.articles-pagination', ['paginationLabel' => 'Сторінки крамниці']) }}
        </div>
    </div>
</section>
@endsection
