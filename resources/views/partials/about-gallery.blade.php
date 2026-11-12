@if ($photos !== [])
    @php
        $slides = count($photos) < 2 ? $photos : [...$photos, ...$photos];
    @endphp
    <section class="about-gallery" aria-labelledby="about-gallery-title" data-about-gallery>
        <h2 id="about-gallery-title">Світлини</h2>

        <div class="about-gallery__viewport">
            <div class="about-gallery__track" id="about-gallery-photos" data-about-gallery-track>
                @foreach ($slides as $photo)
                    <figure class="about-gallery__slide{{ $loop->index === (count($photos) === 1 ? 0 : 1) ? ' is-active' : '' }}">
                        <a href="{{ $photo['url'] }}" data-fancybox="about-center" data-caption="{{ $photo['alt'] }}">
                            @include('partials.publication-image', ['visual' => $photo['visual'], 'imageAlt' => $photo['alt'], 'sizes' => '(min-width: 768px) 602px, calc(100vw - 32px)'])
                        </a>
                    </figure>
                @endforeach
            </div>
        </div>

        @if (count($photos) > 1)
            <div class="about-gallery__controls">
                <button type="button" class="about-gallery__button about-gallery__button--prev" data-about-gallery-prev aria-controls="about-gallery-photos" aria-label="Попередня світлина">
                    <img src="{{ asset('assets/figma/about/arrow-right.svg') }}" alt="">
                </button>
                <button type="button" class="about-gallery__button" data-about-gallery-next aria-controls="about-gallery-photos" aria-label="Наступна світлина">
                    <img src="{{ asset('assets/figma/about/arrow-right.svg') }}" alt="">
                </button>
            </div>
        @endif
    </section>
@endif
