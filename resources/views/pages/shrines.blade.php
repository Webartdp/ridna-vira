@extends('layouts.app')

@section('title', 'Святині — Рідна Віра')
@section('description', 'Святині Рідної Землі у локальному архіві Духовного центру Рідна Віра.')

@php
    $imageFor = static function (array $item): ?string {
        foreach (($item['assets'] ?? []) as $asset) {
            $path = (string) ($asset['path'] ?? '');

            if ($path !== '' && preg_match('/\.(?:jpe?g|png|gif|webp|svg)(?:\?.*)?$/i', $path)) {
                return $path;
            }
        }

        return null;
    };

    $shrineCollection = collect($shrines)
        ->filter(static fn ($shrine): bool => is_array($shrine))
        ->values();
    $groupedShrines = $shrineCollection->groupBy(static function (array $shrine): string {
        $region = trim((string) ($shrine['region'] ?? ''));

        return $region !== '' ? $region : 'Святині';
    });
    $featured = $shrineCollection->first(static fn (array $shrine): bool => $imageFor($shrine) !== null) ?? $shrineCollection->first();
    $featuredImage = is_array($featured) ? $imageFor($featured) : null;
@endphp

@push('styles')
<style>
    .shrines-hero {
        position: relative;
        min-height: 430px;
        display: grid;
        align-items: end;
        overflow: hidden;
        color: #fff;
        background:
            linear-gradient(180deg, rgba(18, 24, 17, .28), rgba(18, 24, 17, .88)),
            url('/assets/figma/home/communities-bg.png') center / cover no-repeat;
    }

    .shrines-hero__content {
        position: relative;
        z-index: 1;
        width: min(1044px, calc(100% - 32px));
        margin-inline: auto;
        padding: 0 0 58px;
    }

    .shrines-hero__eyebrow {
        display: block;
        margin-bottom: 14px;
        color: rgba(255, 255, 255, .78);
        font-family: var(--rv-font-ui);
        font-size: 13px;
        font-weight: 600;
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .shrines-hero h1 {
        max-width: 820px;
        margin: 0;
        font-family: var(--rv-font-display);
        font-size: clamp(48px, 8vw, 94px);
        font-weight: 700;
        line-height: .96;
        text-transform: uppercase;
    }

    .shrines-hero__lead {
        max-width: 670px;
        margin: 22px 0 0;
        color: rgba(255, 255, 255, .86);
        font-family: var(--rv-font-ui);
        font-size: 18px;
        line-height: 1.58;
    }

    .inner-breadcrumbs {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 28px;
        color: rgba(255, 255, 255, .72);
        font-family: var(--rv-font-ui);
        font-size: 13px;
        text-transform: uppercase;
    }

    .inner-breadcrumbs a:hover {
        color: #fff;
    }

    .shrines-page {
        padding: 76px 0 96px;
    }

    .shrines-opening {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(300px, 392px);
        gap: 46px;
        align-items: stretch;
        margin-bottom: 48px;
    }

    .shrines-opening__text {
        display: grid;
        align-content: center;
        padding: 6px 0;
    }

    .shrines-opening__kicker,
    .shrines-feature__region,
    .shrine-place__kind {
        color: #35533b;
        font-family: var(--rv-font-ui);
        font-size: 12px;
        font-weight: 600;
        letter-spacing: .1em;
        text-transform: uppercase;
    }

    .shrines-opening h2 {
        max-width: 660px;
        margin: 10px 0 20px;
        font-family: var(--rv-font-display);
        font-size: clamp(34px, 5vw, 58px);
        font-weight: 700;
        line-height: 1;
        text-transform: uppercase;
    }

    .shrines-opening p {
        max-width: 680px;
        margin: 0;
        color: rgba(28, 8, 3, .78);
        font-family: var(--rv-font-ui);
        font-size: 18px;
        line-height: 1.62;
    }

    .shrines-opening__rule {
        width: min(420px, 100%);
        height: 7px;
        margin-top: 34px;
        background:
            linear-gradient(90deg, #35533b 0 22%, transparent 22% 28%, #c28b2d 28% 46%, transparent 46% 52%, #401403 52% 100%);
    }

    .shrines-feature {
        position: relative;
        min-height: 340px;
        display: flex;
        overflow: hidden;
        align-items: flex-end;
        border: 3px solid #fff;
        border-radius: 8px;
        color: #fff;
        background: #263a2c;
        box-shadow: var(--rv-shadow);
    }

    .shrines-feature img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .shrines-feature::after {
        position: absolute;
        inset: 0;
        content: '';
        background: linear-gradient(180deg, rgba(18, 29, 22, .08), rgba(18, 29, 22, .92));
    }

    .shrines-feature__fallback {
        position: absolute;
        inset: 0;
        background:
            linear-gradient(90deg, rgba(255, 255, 255, .08) 1px, transparent 1px) 0 0 / 34px 34px,
            linear-gradient(0deg, rgba(255, 255, 255, .08) 1px, transparent 1px) 0 0 / 34px 34px,
            #263a2c;
    }

    .shrines-feature__body {
        position: relative;
        z-index: 1;
        padding: 26px;
    }

    .shrines-feature__region {
        color: rgba(255, 255, 255, .72);
    }

    .shrines-feature strong {
        display: block;
        margin-top: 10px;
        font-family: var(--rv-font-display);
        font-size: 31px;
        line-height: 1.08;
        text-transform: uppercase;
    }

    .shrines-region-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 13px 24px;
        margin-bottom: 54px;
        padding: 22px 0;
        border-top: 1px solid rgba(64, 20, 3, .16);
        border-bottom: 1px solid rgba(64, 20, 3, .16);
        font-family: var(--rv-font-ui);
        font-size: 14px;
        text-transform: uppercase;
    }

    .shrines-region-nav a {
        color: rgba(64, 20, 3, .74);
        border-bottom: 1px solid transparent;
    }

    .shrines-region-nav a:hover {
        color: #35533b;
        border-bottom-color: #35533b;
    }

    .shrines-regions {
        display: grid;
        gap: 58px;
    }

    .shrines-region__header {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 24px;
    }

    .shrines-region__header::after {
        height: 1px;
        flex: 1;
        content: '';
        background: rgba(64, 20, 3, .18);
    }

    .shrines-region h3 {
        margin: 0;
        color: #401403;
        font-family: var(--rv-font-display);
        font-size: clamp(27px, 4vw, 40px);
        font-weight: 700;
        line-height: 1;
        text-transform: uppercase;
    }

    .shrines-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 20px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .shrine-place {
        min-height: 286px;
        display: grid;
        grid-template-rows: 164px 1fr;
        overflow: hidden;
        border: 1px solid rgba(64, 20, 3, .16);
        border-radius: 8px;
        background: rgba(255, 255, 255, .64);
        box-shadow: 0 9px 22px rgba(28, 8, 3, .08);
        transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
    }

    .shrine-place:hover {
        border-color: rgba(53, 83, 59, .48);
        box-shadow: 0 14px 30px rgba(28, 8, 3, .16);
        transform: translateY(-3px);
    }

    .shrine-place__media {
        position: relative;
        overflow: hidden;
        background: #d9ded1;
    }

    .shrine-place__media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform .25s ease;
    }

    .shrine-place:hover .shrine-place__media img {
        transform: scale(1.04);
    }

    .shrine-place__fallback {
        width: 100%;
        height: 100%;
        display: grid;
        place-items: center;
        background:
            linear-gradient(90deg, rgba(53, 83, 59, .16) 1px, transparent 1px) 0 0 / 32px 32px,
            linear-gradient(0deg, rgba(53, 83, 59, .12) 1px, transparent 1px) 0 0 / 32px 32px,
            #e6e1d3;
    }

    .shrine-place__marker {
        width: 36px;
        height: 36px;
        border: 3px solid #35533b;
        border-radius: 50% 50% 50% 0;
        transform: rotate(-45deg);
    }

    .shrine-place__body {
        display: grid;
        align-content: space-between;
        min-height: 122px;
        padding: 19px 20px 20px;
    }

    .shrine-place__kind {
        color: rgba(53, 83, 59, .88);
        letter-spacing: .04em;
    }

    .shrine-place strong {
        display: block;
        margin-top: 10px;
        font-family: var(--rv-font-display);
        font-size: 20px;
        font-weight: 700;
        line-height: 1.16;
        text-transform: uppercase;
    }

    .shrine-place__read {
        margin-top: 18px;
        color: #8a5d16;
        font-family: var(--rv-font-ui);
        font-size: 13px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .shrines-empty {
        padding: 44px 0;
        border-top: 1px solid rgba(64, 20, 3, .14);
        border-bottom: 1px solid rgba(64, 20, 3, .14);
    }

    .shrines-empty h3 {
        font-family: var(--rv-font-display);
        font-size: 28px;
        text-transform: uppercase;
    }

    .shrines-empty p {
        max-width: 560px;
        color: rgba(64, 20, 3, .7);
        font-family: var(--rv-font-ui);
        font-size: 16px;
        line-height: 1.5;
    }

    @media (max-width: 991.98px) {
        .shrines-opening {
            grid-template-columns: 1fr;
        }

        .shrines-feature {
            min-height: 292px;
        }

        .shrines-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .shrines-hero {
            min-height: 340px;
        }

        .shrines-hero__content {
            padding-bottom: 40px;
        }

        .shrines-hero__lead {
            font-size: 16px;
        }

        .shrines-page {
            padding: 50px 0 68px;
        }

        .shrines-region__header {
            display: block;
        }

        .shrines-region__header::after {
            display: block;
            width: 100%;
            margin-top: 16px;
        }

        .shrines-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<section class="shrines-hero" aria-labelledby="shrines-page-title">
    <div class="shrines-hero__content">
        <span class="shrines-hero__eyebrow">Духовний центр «Рідна Віра»</span>
        <h1 id="shrines-page-title">Святині Рідної Землі</h1>
        <p class="shrines-hero__lead">Офіційний локальний архів місць сили, давніх святилищ, городищ, могил, печер і природних пам’яток української рідновірської традиції.</p>
        <nav class="inner-breadcrumbs" aria-label="Навігаційний шлях">
            <a href="{{ route('home') }}">Головна</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('faith') }}">Рідна Віра</a>
            <span aria-hidden="true">/</span>
            <span>Святині</span>
        </nav>
    </div>
</section>

<section class="shrines-page">
    <div class="figma-container">
        <header class="shrines-opening">
            <div class="shrines-opening__text">
                <span class="shrines-opening__kicker">Священна географія</span>
                <h2>Жива пам’ять землі</h2>
                <p>Цей розділ збирає матеріали про місця, де природний ландшафт, історична пам’ять і духовна традиція сходяться в одну присутність.</p>
                <span class="shrines-opening__rule" aria-hidden="true"></span>
            </div>

            @if (is_array($featured))
                <a class="shrines-feature" href="{{ route('faith.shrines.show', ['shrine' => $featured['slug']]) }}">
                    @if ($featuredImage)
                        <img src="{{ $featuredImage }}" alt="{{ $featured['title'] }}">
                    @else
                        <span class="shrines-feature__fallback" aria-hidden="true"></span>
                    @endif
                    <span class="shrines-feature__body">
                        <span class="shrines-feature__region">{{ $featured['region'] ?? 'Святиня' }}</span>
                        <strong>{{ $featured['title'] }}</strong>
                    </span>
                </a>
            @endif
        </header>

        @if ($shrineCollection->isEmpty())
            <div class="shrines-empty">
                <h3>Святині ще не імпортовано</h3>
                <p>Після перенесення тут з’явиться локальний список матеріалів.</p>
            </div>
        @else
            <nav class="shrines-region-nav" aria-label="Області святинь">
                @foreach ($groupedShrines as $region => $items)
                    <a href="#shrine-region-{{ $loop->iteration }}">{{ $region }}</a>
                @endforeach
            </nav>

            <div class="shrines-regions">
                @foreach ($groupedShrines as $region => $items)
                    <section class="shrines-region" id="shrine-region-{{ $loop->iteration }}" aria-labelledby="shrine-region-title-{{ $loop->iteration }}">
                        <header class="shrines-region__header">
                            <h3 id="shrine-region-title-{{ $loop->iteration }}">{{ $region }}</h3>
                        </header>

                        <ol class="shrines-grid" aria-label="Святині: {{ $region }}">
                            @foreach ($items as $shrine)
                                @php
                                    $imagePath = $imageFor($shrine);
                                @endphp
                                <li>
                                    <a class="shrine-place" href="{{ route('faith.shrines.show', ['shrine' => $shrine['slug']]) }}">
                                        <span class="shrine-place__media">
                                            @if ($imagePath)
                                                <img src="{{ $imagePath }}" alt="{{ $shrine['title'] }}">
                                            @else
                                                <span class="shrine-place__fallback" aria-hidden="true"><span class="shrine-place__marker"></span></span>
                                            @endif
                                        </span>
                                        <span class="shrine-place__body">
                                            <span>
                                                <span class="shrine-place__kind">Святиня</span>
                                                <strong>{{ $shrine['title'] }}</strong>
                                            </span>
                                            <span class="shrine-place__read">Відкрити матеріал</span>
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection
