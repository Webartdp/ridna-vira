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
        .shop-catalogue .shop-card h2{font-size:1.125rem;line-height:1.35}
        .shop-catalogue .shop-card img{height:auto;aspect-ratio:1;max-height:260px}
        .shop-catalogue .shop-card .shop-btn{margin-top:auto;text-align:center;padding-inline:10px}
        .shop .shop-maker-title{font-size:clamp(1.625rem,3vw,2.25rem);margin-bottom:6px;overflow-wrap:anywhere}
        .shop-maker-name{margin-bottom:6px;overflow-wrap:anywhere}
        .shop-category-links ul{display:flex;flex-wrap:wrap;gap:8px;list-style:none;margin:18px 0 0;padding:0}
        .shop-category-links a{display:inline-flex;align-items:center;min-height:44px;padding:8px 12px;border:1px solid #d7cab3;border-radius:8px;background:#fffdf6;font-size:.875rem;line-height:1.4;overflow-wrap:anywhere}
        .shop-category-links a:hover{background:#f3e8d5;border-color:#9b7845}
        @media(max-width:900px){.shop-catalogue{grid-template-columns:minmax(0,1fr)}}

        .shop-catalogue .articles-pager {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 12px;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #d7cab3;
            font-size: .875rem;
            line-height: 1;
        }

        .shop-catalogue .articles-pager__pages {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 4px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .shop-catalogue :is(.articles-pager__step, .articles-pager__number) {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            border: 1px solid #cbb797;
            border-radius: 8px;
            background: #fffdf6;
            color: #493a23;
            text-decoration: none;
        }

        .shop-catalogue .articles-pager__number {
            min-width: 44px;
            padding: 0 8px;
        }

        .shop-catalogue .articles-pager__step {
            justify-self: end;
            gap: 8px;
            padding: 0 12px;
            white-space: nowrap;
        }

        .shop-catalogue .articles-pager__step--next { justify-self: start; }
        .shop-catalogue .articles-pager__step span { font: 1.125rem/1 system-ui, sans-serif; }
        .shop-catalogue .articles-pager__gap { padding-inline: 2px; color: #765e3e; }

        .shop-catalogue .articles-pager a:hover {
            border-color: #9b7845;
            background: #f3e8d5;
            color: #382c1a;
        }

        .shop-catalogue .articles-pager a:focus-visible {
            outline: 2px solid #785629;
            outline-offset: 2px;
        }

        .shop-catalogue .articles-pager__number.is-current {
            border-color: #493a23;
            background: #493a23;
            color: #fffdf6;
            font-weight: 700;
        }

        .shop-catalogue .articles-pager__step.is-disabled {
            border-color: #dfd4c2;
            background: transparent;
            color: #756959;
        }

        @media (max-width: 600px) {
            .shop-catalogue .articles-pager {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .shop-catalogue .articles-pager__pages { grid-area: 1 / 1 / 2 / -1; }
        }
    </style>
@endpush
@push('scripts')
    <script type="module">
        const filters = document.querySelector('.shop-filters');
        filters.addEventListener('change', event => {
            if (event.target.matches('input[name="category"]')) filters.requestSubmit();
        });
    </script>
@endpush
@section('content')
<section class="shop">
    @if ($maker ?? null)
        <h1 class="shop-maker-title">Вироби майстра</h1>
        <p class="shop-maker-name">{{ $maker->name }}</p>
        <small><a href="{{ route('shop.index') }}">Усі товари</a></small>
    @elseif ($browseCategory)
        @include('shop.breadcrumbs', ['trail' => \App\Support\ShopCatalogueSeo::categoryTrail($browseCategory)])
        <h1>{{ $browseCategory->name }}</h1>
        <p>{{ $categoryDescription }}</p>
    @else
        <h1>Крамниця</h1>
        <p>Книги, речі та видання, що підтримують живу традицію.</p>
        @guest
            <p><a href="{{ route('maker.register') }}">Пропонувати свої вироби</a></p>
        @elseif (in_array(auth()->user()->roleName(), ['user', 'maker'], true))
            <p><a href="{{ route('maker.index') }}">Кабінет майстра</a></p>
        @endguest
    @endif
    @if (!$maker && $categoryLinks->isNotEmpty())
        <nav class="shop-category-links" aria-label="{{ $browseCategory ? 'Підкатегорії крамниці' : 'Категорії крамниці' }}">
            <ul>
                @foreach ($categoryLinks as $categoryLink)
                    <li><a href="{{ $categoryLink->publicUrl() }}">{{ $categoryLink->name }}</a></li>
                @endforeach
            </ul>
        </nav>
    @endif
    @php
        $priceFrom = old('price_from', request('price_from'));
        $priceTo = old('price_to', request('price_to'));
        $hasFilters = request()->anyFilled(['q', 'category', 'price_from', 'price_to']);
    @endphp
    <div class="shop-catalogue">
        <form method="get" action="{{ route('shop.index') }}" class="shop-panel shop-filters" aria-labelledby="shop-filters-title">
            @if ($maker ?? null)
                <input type="hidden" name="maker" value="{{ $maker->id }}">
            @endif
            <h2 id="shop-filters-title">Фільтри</h2>
            <div>
                <label for="shop-search">Пошук</label>
                <input id="shop-search" name="q" value="{{ request()->string('q', strict: true) }}" maxlength="100" placeholder="Назва товару">
            </div>
            <fieldset>
                <legend>Ціна, ₴</legend>
                @include('shop.price-slider', ['minimum' => 0, 'maximum' => $sliderMaximum])
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
                <a class="shop-filters-reset" href="{{ $browseCategory?->publicUrl() ?? route('shop.index', $maker ? ['maker' => $maker->id] : []) }}">Скинути фільтри</a>
            @endif
        </form>
        <div>
            <div class="shop-grid">
                @forelse($products as $product)
                    <article class="shop-card">
                        <a href="{{ route('shop.product', $product) }}">@if($product->images)<img src="{{ $product->imageUrl($product->images[0], 400) }}" srcset="{{ $product->imageSrcset($product->images[0]) }}" sizes="(min-width: 1240px) 260px, (min-width: 901px) 22vw, (min-width: 541px) 44vw, 85vw" alt="{{ $product->name }}" loading="lazy" decoding="async" width="400" height="400">@else<img src="{{ asset('assets/brand/image-placeholder.svg') }}" width="400" height="400" alt="" loading="lazy" decoding="async">@endif</a>
                        <h2><a href="{{ route('shop.product', $product, false) }}" data-shop-product>{{ $product->name }}</a></h2>
                        <span class="shop-price">{{ \App\Support\Money::display($product->price_minor) }}</span>
                        <small>{{ $product->availabilityLabel() }}</small>
                        <a class="shop-btn" href="{{ route('shop.product', $product) }}">Переглянути</a>
                    </article>
                @empty
                    <div class="shop-panel shop-empty" style="grid-column:1/-1">
                        @if ($hasFilters)
                            <h2>Товарів не знайдено</h2>
                            <p>Спробуйте змінити фільтри.</p>
                        @elseif ($maker ?? null)
                            <h2>Виробів поки немає</h2>
                        @else
                            <h2>Готуємо крамницю до відкриття</h2>
                            <p>Незабаром тут з’являться наші книги та інші товари.</p>
                        @endif
                    </div>
                @endforelse
            </div>
            {{ $products->links('partials.articles-pagination', ['paginationLabel' => 'Сторінки крамниці']) }}
            @if ($catalogueCopy)
                @include('shop.catalogue-copy', [
                    'heading' => $browseCategory ? 'Про розділ «'.$browseCategory->name.'»' : 'Про крамницю «Рідна Віра»',
                    'sections' => $catalogueCopy,
                ])
            @endif
        </div>
    </div>
</section>
@endsection
