@extends('layouts.app')

@section('title', 'Святині — Рідна Віра')
@section('description', 'Локально збережений розділ святинь Духовного центру Рідна Віра.')

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
    $regionsCount = $groupedShrines->count();
    $imagesCount = (int) $shrineCollection->sum(static fn (array $shrine): int => (int) ($shrine['images'] ?? 0));
    $featured = $shrineCollection->first(static fn (array $shrine): bool => ($shrine['images'] ?? 0) > 0) ?? $shrineCollection->first();
    $featuredImage = is_array($featured) ? $imageFor($featured) : null;
@endphp

@push('styles')
<style>
    .inner-hero--shrines {
        position: relative;
        min-height: 324px;
        display: grid;
        align-items: end;
        overflow: hidden;
        color: #fff;
        background:
            linear-gradient(180deg, rgba(28, 8, 3, .38), rgba(28, 8, 3, .78)),
            url('/assets/figma/home/communities-bg.png') center / cover no-repeat;
    }

    .inner-hero--shrines .inner-hero__content {
        position: relative;
        z-index: 1;
        width: min(1044px, calc(100% - 32px));
        margin-inline: auto;
        padding: 0 0 54px;
    }

    .inner-hero--shrines h1 {
        margin: 0 0 16px;
        font-family: var(--rv-font-display);
        font-size: clamp(48px, 8vw, 94px);
        font-weight: 700;
        line-height: .95;
        text-transform: uppercase;
    }

    .inner-breadcrumbs {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        color: rgba(255, 255, 255, .78);
        font-family: var(--rv-font-ui);
        font-size: 14px;
        text-transform: uppercase;
    }

    .inner-breadcrumbs a:hover {
        color: #fff;
    }

    .shrines-page {
        padding: 72px 0 90px;
    }

    .shrines-intro {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(300px, 378px);
        gap: 42px;
        align-items: stretch;
        margin-bottom: 40px;
        padding-bottom: 36px;
        border-bottom: 1px solid rgba(64, 20, 3, .18);
    }

    .shrines-intro__eyebrow,
    .shrines-feature__region,
    .shrine-place__label {
        color: #35533b;
        font-family: var(--rv-font-ui);
        font-size: 12px;
        font-weight: 600;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .shrines-intro h2 {
        max-width: 650px;
        margin: 8px 0 18px;
        font-family: var(--rv-font-display);
        font-size: clamp(34px, 5vw, 58px);
        font-weight: 700;
        line-height: 1;
        text-transform: uppercase;
    }

    .shrines-intro p {
        max-width: 660px;
        margin-bottom: 30px;
        color: rgba(28, 8, 3, .78);
        font-family: var(--rv-font-ui);
        font-size: 18px;
        line-height: 1.55;
    }

    .shrines-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 132px));
        gap: 18px;
        margin: 0;
    }

    .shrines-stats div {
        padding-top: 13px;
        border-top: 3px solid #35533b;
    }

    .shrines-stats dt {
        color: #401403;
        font-family: var(--rv-font-display);
        font-size: 34px;
        font-weight: 700;
        line-height: 1;
    }

    .shrines-stats dd {
        margin: 7px 0 0;
        color: rgba(64, 20, 3, .7);
        font-family: var(--rv-font-ui);
        font-size: 13px;
        text-transform: uppercase;
    }

    .shrines-feature {
        position: relative;
        min-height: 318px;
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
        background: linear-gradient(180deg, rgba(18, 29, 22, .12), rgba(18, 29, 22, .9));
    }

    .shrines-feature__fallback {
        position: absolute;
        inset: 0;
        background:
            linear-gradient(135deg, rgba(255, 255, 255, .08) 25%, transparent 25%) 0 0 / 28px 28px,
            linear-gradient(135deg, transparent 75%, rgba(255, 255, 255, .08) 75%) 0 0 / 28px 28px,
            #263a2c;
    }

    .shrines-feature__body {
        position: relative;
        z-index: 1;
        padding: 24px;
    }

    .shrines-feature__region {
        color: rgba(255, 255, 255, .72);
    }

    .shrines-feature strong {
        display: block;
        margin-top: 9px;
        font-family: var(--rv-font-display);
        font-size: 30px;
        line-height: 1.08;
        text-transform: uppercase;
    }

    .shrines-feature small {
        display: block;
        margin-top: 13px;
        color: rgba(255, 255, 255, .78);
        font-family: var(--rv-font-ui);
        font-size: 14px;
    }

    .shrines-region-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 12px 22px;
        margin-bottom: 42px;
        padding-bottom: 24px;
        border-bottom: 1px solid rgba(64, 20, 3, .12);
        font-family: var(--rv-font-ui);
        font-size: 14px;
        text-transform: uppercase;
    }

    .shrines-region-nav a {
        color: rgba(64, 20, 3, .72);
        border-bottom: 1px solid transparent;
    }

    .shrines-region-nav a:hover {
        color: #35533b;
        border-bottom-color: #35533b;
    }

    .shrines-regions {
        display: grid;
        gap: 52px;
    }

    .shrines-region__header {
        display: grid;
        grid-template-columns: 56px minmax(0, 1fr) auto;
        gap: 20px;
        align-items: end;
        margin-bottom: 20px;
    }

    .shrines-region__number {
        color: #35533b;
        font-family: var(--rv-font-display);
        font-size: 42px;
        font-weight: 700;
        line-height: .85;
    }

    .shrines-region h3 {
        margin: 0;
        font-family: var(--rv-font-display);
        font-size: clamp(26px, 4vw, 38px);
        font-weight: 700;
        line-height: 1;
        text-transform: uppercase;
    }

    .shrines-region__count {
        color: rgba(64, 20, 3, .62);
        font-family: var(--rv-font-ui);
        font-size: 13px;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .shrines-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .shrine-place {
        min-height: 264px;
        display: grid;
        grid-template-rows: 148px 1fr;
        overflow: hidden;
        border: 1px solid rgba(64, 20, 3, .16);
        border-radius: 8px;
        background: rgba(255, 255, 255, .58);
        box-shadow: 0 9px 22px rgba(28, 8, 3, .08);
        transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
    }

    .shrine-place:hover {
        border-color: rgba(53, 83, 59, .45);
        box-shadow: 0 13px 28px rgba(28, 8, 3, .14);
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
            linear-gradient(90deg, rgba(53, 83, 59, .18) 1px, transparent 1px) 0 0 / 28px 28px,
            linear-gradient(0deg, rgba(53, 83, 59, .14) 1px, transparent 1px) 0 0 / 28px 28px,
            #e6e1d3;
    }

    .shrine-place__marker {
        width: 34px;
        height: 34px;
        border: 3px solid #35533b;
        border-radius: 50% 50% 50% 0;
        transform: rotate(-45deg);
    }

    .shrine-place__index {
        position: absolute;
        top: 12px;
        left: 12px;
        min-width: 38px;
        height: 28px;
        display: grid;
        place-items: center;
        color: #fff;
        background: rgba(28, 8, 3, .78);
        font-family: var(--rv-font-ui);
        font-size: 12px;
    }

    .shrine-place__body {
        display: flex;
        min-height: 116px;
        flex-direction: column;
        justify-content: space-between;
        padding: 17px 18px 18px;
    }

    .shrine-place__label {
        color: rgba(53, 83, 59, .86);
        letter-spacing: 0;
    }

    .shrine-place strong {
        display: block;
        margin: 10px 0 16px;
        font-family: var(--rv-font-display);
        font-size: 19px;
        font-weight: 700;
        line-height: 1.16;
        text-transform: uppercase;
    }

    .shrine-place__meta {
        color: rgba(64, 20, 3, .64);
        font-family: var(--rv-font-ui);
        font-size: 13px;
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
        .shrines-intro {
            grid-template-columns: 1fr;
        }

        .shrines-feature {
            min-height: 278px;
        }

        .shrines-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .inner-hero--shrines {
            min-height: 248px;
        }

        .inner-hero--shrines .inner-hero__content {
            padding-bottom: 36px;
        }

        .shrines-page {
            padding: 48px 0 64px;
        }

        .shrines-stats {
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .shrines-stats dt {
            font-size: 26px;
        }

        .shrines-region__header {
            grid-template-columns: 42px minmax(0, 1fr);
        }

        .shrines-region__count {
            grid-column: 2;
        }

        .shrines-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<section class="inner-hero inner-hero--shrines" aria-labelledby="shrines-page-title">
    <div class="inner-hero__content">
        <h1 id="shrines-page-title">Святині</h1>
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
        <header class="shrines-intro">
            <div class="shrines-intro__copy">
                <span class="shrines-intro__eyebrow">Священні місця</span>
                <h2>Святині Рідної Землі</h2>
                <p>Місця сили, давні святилища, городища, могили, печери й природні пам’ятки, перенесені до локального архіву сайту.</p>

                <dl class="shrines-stats" aria-label="Підсумок архіву святинь">
                    <div>
                        <dt>{{ $shrineCollection->count() }}</dt>
                        <dd>святинь</dd>
                    </div>
                    <div>
                        <dt>{{ $regionsCount }}</dt>
                        <dd>областей</dd>
                    </div>
                    <div>
                        <dt>{{ $imagesCount }}</dt>
                        <dd>зображень</dd>
                    </div>
                </dl>
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
                        <small>{{ !empty($featured['images']) ? $featured['images'].' зобр.' : 'Локальний матеріал' }}</small>
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
                            <span class="shrines-region__number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <h3 id="shrine-region-title-{{ $loop->iteration }}">{{ $region }}</h3>
                            <span class="shrines-region__count">{{ $items->count() }} {{ $items->count() === 1 ? 'місце' : 'місць' }}</span>
                        </header>

                        <ol class="shrines-grid" aria-label="Святині: {{ $region }}">
                            @foreach ($items as $shrine)
                                @php
                                    $imagePath = $imageFor($shrine);
                                    $characters = (int) ($shrine['characters'] ?? 0);
                                    $images = (int) ($shrine['images'] ?? 0);
                                @endphp
                                <li>
                                    <a class="shrine-place" href="{{ route('faith.shrines.show', ['shrine' => $shrine['slug']]) }}">
                                        <span class="shrine-place__media">
                                            @if ($imagePath)
                                                <img src="{{ $imagePath }}" alt="{{ $shrine['title'] }}">
                                            @else
                                                <span class="shrine-place__fallback" aria-hidden="true"><span class="shrine-place__marker"></span></span>
                                            @endif
                                            <span class="shrine-place__index">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                        </span>
                                        <span class="shrine-place__body">
                                            <span>
                                                <span class="shrine-place__label">{{ $images > 0 ? 'Матеріал з фото' : 'Текстовий матеріал' }}</span>
                                                <strong>{{ $shrine['title'] }}</strong>
                                            </span>
                                            <span class="shrine-place__meta">
                                                @if ($characters > 0)
                                                    {{ number_format($characters, 0, ',', ' ') }} знаків
                                                @else
                                                    локальний архів
                                                @endif
                                                @if ($images > 0)
                                                    · {{ $images }} зобр.
                                                @endif
                                            </span>
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
