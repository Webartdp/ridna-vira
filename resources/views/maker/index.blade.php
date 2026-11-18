@extends('account.layout', ['accountTitle' => 'Кабінет майстра'])

@push('styles') @include('maker.styles') @endpush

@section('account-content')
@if ($products === null)
    <div class="shop-panel account-form">
        <p>Відкрийте майстерню, щоб пропонувати свої вироби в крамниці.</p>
        @if (auth()->user()->hasVerifiedEmail())
            <form method="post" action="{{ route('maker.activate') }}">
                @csrf
                <button class="shop-btn" type="submit">Відкрити майстерню</button>
            </form>
        @else
            <a href="{{ route('verification.notice') }}">Підтвердити електронну пошту</a>
        @endif
    </div>
@elseif (!auth()->user()->hasVerifiedEmail())
    <div class="shop-panel">
        <p>Підтвердьте електронну пошту, щоб додавати вироби.</p>
        <a href="{{ route('verification.notice') }}">Перейти до підтвердження</a>
    </div>
@else
    <div class="shop-split">
        @include('maker.form', ['product' => null])
        <section class="shop-panel" aria-labelledby="maker-products">
            <h2 id="maker-products">Мої вироби</h2>
            @forelse ($products as $product)
                <article class="maker-product">
                    <img class="maker-thumb" src="{{ $product->images ? $product->imageUrl($product->images[0], 400) : asset('assets/brand/image-placeholder.svg') }}"
                        width="64" height="64" loading="lazy" decoding="async" alt="">
                    <div class="maker-product-info">
                        <h3>@if ($product->is_active)<a href="{{ route('shop.product', $product) }}">{{ $product->name }}</a>@else{{ $product->name }}@endif</h3>
                        <small>{{ $product->is_active ? 'У крамниці' : 'Не опубліковано' }} · {{ $product->availabilityLabel() }}</small>
                        <small>Ваша ціна {{ \App\Support\Money::display($product->maker_price_minor) }}</small>
                        <small>У крамниці {{ \App\Support\Money::display($product->price_minor) }}</small>
                    </div>
                    <div class="maker-actions">
                        <a class="maker-action" href="{{ route('maker.products.edit', $product) }}" aria-label="Редагувати {{ $product->name }}" title="Редагувати">
                            @svg('heroicon-o-pencil-square', 'maker-icon')
                        </a>
                        @if ($product->is_active)
                            <form method="post" action="{{ route('maker.products.hide', $product) }}">
                                @csrf @method('PATCH')
                                <button class="maker-action" type="submit" aria-label="Приховати {{ $product->name }}" title="Приховати">
                                    @svg('heroicon-o-eye-slash', 'maker-icon')
                                </button>
                            </form>
                        @endif
                        <form method="post" action="{{ route('maker.products.destroy', $product) }}" onsubmit="return confirm('Видалити цей виріб?')">
                            @csrf @method('DELETE')
                            <button class="maker-action maker-action-danger" type="submit" aria-label="Видалити {{ $product->name }}" title="Видалити">
                                @svg('heroicon-o-trash', 'maker-icon')
                            </button>
                        </form>
                    </div>
                </article>
            @empty
                <p>Ваші вироби з’являться тут після додавання.</p>
            @endforelse
            {{ $products->links('partials.articles-pagination') }}
        </section>
    </div>
@endif
@endsection
