@if($products->isNotEmpty())
    <section class="home-latest-products" aria-labelledby="home-latest-products-title">
        <div class="figma-container">
            <div class="home-latest-products__heading">
                <h2 id="home-latest-products-title">Нові товари крамниці</h2>
                <a href="{{ route('shop.index') }}">Усі товари <span aria-hidden="true">→</span></a>
            </div>

            <ul class="home-latest-products__grid">
                @foreach($products as $product)
                    @php
                        $image = $product->images[0] ?? null;
                        $srcset = $image ? $product->imageSrcset($image) : '';
                    @endphp
                    <li>
                        <a class="home-latest-products__card" href="{{ route('shop.product', $product) }}">
                            <img
                                src="{{ $image ? $product->imageUrl($image, 400) : asset('assets/brand/image-placeholder.svg') }}"
                                @if($srcset)
                                    srcset="{{ $srcset }}"
                                    sizes="(min-width: 1076px) 249px, (min-width: 900px) calc((100vw - 80px) / 4), (min-width: 480px) calc((100vw - 48px) / 2), calc(100vw - 32px)"
                                @endif
                                alt=""
                                width="400"
                                height="400"
                                loading="lazy"
                                decoding="async"
                            >
                            <div class="home-latest-products__details">
                                <h3>{{ $product->name }}</h3>
                                <p>{{ \App\Support\Money::display($product->price_minor) }}</p>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
