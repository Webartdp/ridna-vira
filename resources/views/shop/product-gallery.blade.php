@php
    $photos = array_values($product->images ?? []);
    $multiplePhotos = count($photos) > 1;
    $sizes = $multiplePhotos
        ? '(min-width: 1240px) 492px, (min-width: 901px) calc(50vw - 128px), (min-width: 541px) calc(100vw - 136px), calc(100vw - 32px)'
        : '(min-width: 1240px) 580px, (min-width: 901px) calc(50vw - 40px), (min-width: 541px) calc(100vw - 48px), calc(100vw - 32px)';
@endphp

<div @class(['product-gallery', 'product-gallery--multiple' => $multiplePhotos]) @if ($multiplePhotos) data-product-gallery role="group" aria-label="Фото товару" @endif>
    <div class="swiper product-gallery__stage">
        <div class="swiper-wrapper">
            @forelse ($photos as $photo)
                <div class="swiper-slide">
                    <img class="product-gallery__photo" src="{{ $product->imageUrl($photo) }}"
                        srcset="{{ $product->imageSrcset($photo) }}" sizes="{{ $sizes }}"
                        width="800" height="800" alt="{{ $product->name }}" decoding="async" draggable="false"
                        @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif>
                </div>
            @empty
                <div class="swiper-slide">
                    <img class="product-gallery__photo" src="{{ asset('assets/brand/image-placeholder.svg') }}"
                        width="800" height="800" alt="" decoding="async">
                </div>
            @endforelse
        </div>
        @if ($multiplePhotos)
            <div class="product-gallery__arrows">
                <button type="button" data-gallery-prev aria-label="Попереднє фото">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 5-7 7 7 7"/></svg>
                </button>
                <button type="button" data-gallery-next aria-label="Наступне фото">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg>
                </button>
            </div>
        @endif
    </div>
    @if ($multiplePhotos)
        <div class="product-gallery__rail">
            <div class="swiper product-gallery__thumbnails">
                <div class="swiper-wrapper">
                    @foreach ($photos as $photo)
                        <div class="swiper-slide">
                            <a href="{{ $product->imageUrl($photo) }}" aria-label="Показати фото {{ $loop->iteration }} з {{ $loop->count }}" draggable="false"
                                @if ($loop->first) aria-current="true" @endif>
                                <img src="{{ $product->imageUrl($photo, 400) }}" width="68" height="68" alt="" loading="lazy" decoding="async" draggable="false">
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>

@push('styles')
    @if ($multiplePhotos)
        @vite('resources/js/product-gallery.js')
    @endif
    <style>
        .shop-product-layout{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}
        .product-gallery{min-width:0;align-self:start}
        .product-gallery--multiple{
            display:grid;grid-template-columns:76px minmax(0,1fr);
            grid-template-areas:"rail stage";gap:12px;
        }
        .product-gallery__stage{
            grid-area:stage;position:relative;width:100%;min-width:0;aspect-ratio:1;
            overflow:hidden;border:1px solid #d7cab3;border-radius:14px;background:#fffdf6;
        }
        .product-gallery__stage .swiper-wrapper,
        .product-gallery__stage .swiper-slide{height:100%}
        .product-gallery__photo{display:block;width:100%;height:100%;object-fit:contain}
        .product-gallery__rail{grid-area:rail;position:relative}
        .product-gallery__thumbnails{position:absolute;inset:0;padding:2px}
        .product-gallery__thumbnails .swiper-slide{height:68px}
        .product-gallery__thumbnails:not(.swiper-initialized){overflow:auto}
        .product-gallery__thumbnails:not(.swiper-initialized) .swiper-wrapper{gap:8px}
        .product-gallery__thumbnails a{
            display:block;width:100%;height:100%;padding:3px;
            border:1px solid #d7cab3;border-radius:9px;background:#fffdf6;
        }
        .product-gallery__thumbnails a[aria-current="true"]{border-color:#765931;box-shadow:inset 0 0 0 1px #765931}
        .product-gallery__thumbnails a:focus-visible,
        .product-gallery__arrows button:focus-visible{outline:2px solid #765931;outline-offset:1px}
        .product-gallery__thumbnails img{display:block;width:100%;height:100%;object-fit:cover;border-radius:5px}
        .product-gallery__arrows{
            position:absolute;inset:0 10px;z-index:2;display:none;align-items:center;
            justify-content:space-between;pointer-events:none;
        }
        .product-gallery__stage.swiper-initialized .product-gallery__arrows{display:flex}
        .product-gallery__arrows button{
            display:grid;place-items:center;width:42px;height:42px;padding:0;
            border:1px solid #d7cab3;border-radius:50%;background:#fffdf6;color:#493a23;
            box-shadow:0 2px 8px #302a221a;cursor:pointer;pointer-events:auto;
        }
        .product-gallery__arrows button:hover{background:#f3e8ce}
        @media(min-width:541px){
            .product-gallery__thumbnails:not(.swiper-initialized) .swiper-wrapper{flex-direction:column}
        }
        @media(max-width:900px){.shop-product-layout{grid-template-columns:minmax(0,1fr)}}
        @media(max-width:540px){
            .product-gallery--multiple{grid-template-columns:minmax(0,1fr);grid-template-areas:"stage" "rail";gap:8px}
            .product-gallery__thumbnails{position:relative;height:66px}
            .product-gallery__thumbnails .swiper-slide{width:62px;height:62px}
        }
    </style>
@endpush
