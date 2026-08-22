@extends('layouts.app')

@section('title', 'Святині — Рідна Віра')
@section('description', 'Святині Рідної Землі: місця сили, давні святилища, городища, могили, печери та природні пам’ятки Рідної Віри.')

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
@endphp

@push('styles')
<style>
    .shrine-calendar-page {
        padding: 70px 0 92px;
    }

    .shrine-calendar-intro {
        max-width: 760px;
        margin: 0 auto 50px;
        text-align: center;
    }

    .shrine-calendar-intro__eyebrow {
        display: block;
        margin-bottom: 12px;
        color: #c28b2d;
        font-family: var(--rv-font-ui);
        font-size: 14px;
        text-transform: uppercase;
    }

    .shrine-calendar-intro h2 {
        margin-bottom: 18px;
        font-family: var(--rv-font-display);
        font-size: clamp(32px, 5vw, 54px);
        font-weight: 700;
        line-height: 1;
        text-transform: uppercase;
    }

    .shrine-calendar-intro p {
        margin: 0;
        color: rgba(28, 8, 3, .78);
        font-family: var(--rv-font-ui);
        font-size: 18px;
        line-height: 1.5;
    }

    .shrine-calendar-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
        margin-bottom: 62px;
    }

    .shrine-photo-card {
        position: relative;
        min-height: 247px;
        display: flex;
        overflow: hidden;
        align-items: flex-end;
        border: 3px solid #fff;
        border-radius: 20px;
        color: #fff;
        background: linear-gradient(180deg, var(--rv-paper-start) 0%, var(--rv-paper-end) 100%);
        box-shadow: var(--rv-shadow);
        transition: transform .2s ease, box-shadow .2s ease;
    }

    .shrine-photo-card:hover {
        color: #fff;
        transform: translateY(-5px);
        box-shadow: 0 10px 18px rgba(28, 8, 3, .35);
    }

    .shrine-photo-card__image,
    .shrine-photo-card__fallback {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
    }

    .shrine-photo-card__image {
        object-fit: cover;
        transition: transform .24s ease;
    }

    .shrine-photo-card:hover .shrine-photo-card__image {
        transform: scale(1.05);
    }

    .shrine-photo-card__fallback {
        display: grid;
        place-items: center;
        background:
            linear-gradient(90deg, rgba(64, 20, 3, .12) 1px, transparent 1px) 0 0 / 32px 32px,
            linear-gradient(0deg, rgba(64, 20, 3, .10) 1px, transparent 1px) 0 0 / 32px 32px,
            linear-gradient(180deg, var(--rv-paper-start) 0%, var(--rv-paper-end) 100%);
    }

    .shrine-photo-card__sigil {
        position: relative;
        width: 62px;
        height: 62px;
        border: 3px solid #c28b2d;
        border-radius: 50%;
    }

    .shrine-photo-card__sigil::before,
    .shrine-photo-card__sigil::after {
        position: absolute;
        content: '';
        background: #c28b2d;
    }

    .shrine-photo-card__sigil::before {
        top: 50%;
        left: 10px;
        width: 36px;
        height: 3px;
        transform: translateY(-50%);
    }

    .shrine-photo-card__sigil::after {
        top: 10px;
        left: 50%;
        width: 3px;
        height: 36px;
        transform: translateX(-50%);
    }

    .shrine-photo-card::after {
        position: absolute;
        inset: 35% 0 0;
        content: '';
        background: linear-gradient(180deg, rgba(28, 8, 3, 0), rgba(28, 8, 3, .84));
    }

    .shrine-photo-card__body {
        position: relative;
        z-index: 1;
        width: 100%;
        padding: 20px 18px 18px;
        text-align: center;
    }

    .shrine-photo-card__region {
        display: block;
        margin-bottom: 8px;
        color: rgba(255, 255, 255, .76);
        font-family: var(--rv-font-ui);
        font-size: 12px;
        line-height: 1.2;
        text-transform: uppercase;
    }

    .shrine-photo-card strong {
        display: block;
        font-family: var(--rv-font-display);
        font-size: 18px;
        font-weight: 700;
        line-height: 1.08;
        text-transform: uppercase;
    }

    .shrine-region-index {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 12px 20px;
        margin-bottom: 48px;
        padding: 22px 0;
        border-top: 1px solid rgba(64, 20, 3, .16);
        border-bottom: 1px solid rgba(64, 20, 3, .16);
        font-family: var(--rv-font-ui);
        font-size: 14px;
        text-transform: uppercase;
    }

    .shrine-region-index a {
        color: rgba(64, 20, 3, .74);
        border-bottom: 1px solid transparent;
    }

    .shrine-region-index a:hover {
        color: #c28b2d;
        border-bottom-color: #c28b2d;
    }

    .shrine-region-list {
        display: grid;
        gap: 42px;
    }

    .shrine-region-block {
        display: grid;
        grid-template-columns: 170px minmax(0, 1fr);
        gap: 28px;
        align-items: start;
    }

    .shrine-region-block__title {
        position: sticky;
        top: 20px;
        padding-top: 13px;
        border-top: 4px solid #c28b2d;
    }

    .shrine-region-block__title h3 {
        margin: 0;
        font-family: var(--rv-font-display);
        font-size: 22px;
        font-weight: 700;
        line-height: 1.1;
        text-transform: uppercase;
    }

    .shrine-region-block__cards {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .shrine-mini-card {
        position: relative;
        min-height: 132px;
        display: grid;
        grid-template-columns: 112px minmax(0, 1fr);
        overflow: hidden;
        border: 3px solid #fff;
        border-radius: 20px;
        background: linear-gradient(180deg, var(--rv-paper-start) 0%, var(--rv-paper-end) 100%);
        box-shadow: var(--rv-shadow);
        transition: transform .2s ease;
    }

    .shrine-mini-card:hover {
        transform: translateY(-4px);
    }

    .shrine-mini-card__media {
        min-height: 132px;
        background: rgba(194, 139, 45, .12);
    }

    .shrine-mini-card__media img,
    .shrine-mini-card__fallback {
        width: 100%;
        height: 100%;
    }

    .shrine-mini-card__media img {
        object-fit: cover;
    }

    .shrine-mini-card__fallback {
        display: grid;
        place-items: center;
        background: linear-gradient(180deg, rgba(194, 139, 45, .22), rgba(255, 255, 255, .2));
    }

    .shrine-mini-card__fallback span {
        width: 34px;
        height: 34px;
        border: 2px solid #c28b2d;
        border-radius: 50%;
    }

    .shrine-mini-card__body {
        display: grid;
        align-content: center;
        padding: 16px;
    }

    .shrine-mini-card strong {
        font-family: var(--rv-font-display);
        font-size: 16px;
        font-weight: 700;
        line-height: 1.12;
        text-transform: uppercase;
    }

    .shrines-empty {
        padding: 44px 0;
        border-top: 1px solid rgba(64, 20, 3, .14);
        border-bottom: 1px solid rgba(64, 20, 3, .14);
        text-align: center;
    }

    .shrines-empty h3 {
        font-family: var(--rv-font-display);
        font-size: 28px;
        text-transform: uppercase;
    }

    .shrines-empty p {
        margin: 0 auto;
        max-width: 560px;
        color: rgba(64, 20, 3, .7);
        font-family: var(--rv-font-ui);
        font-size: 16px;
        line-height: 1.5;
    }

    @media (max-width: 1199.98px) {
        .shrine-calendar-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .shrine-region-block__cards {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 991.98px) {
        .shrine-region-block {
            grid-template-columns: 1fr;
            gap: 18px;
        }

        .shrine-region-block__title {
            position: static;
        }
    }

    @media (max-width: 767.98px) {
        .shrine-calendar-page {
            padding: 48px 0 66px;
        }

        .shrine-calendar-grid,
        .shrine-region-block__cards {
            grid-template-columns: 1fr;
        }

        .shrine-photo-card {
            min-height: 224px;
        }

        .shrine-mini-card {
            grid-template-columns: 104px minmax(0, 1fr);
        }
    }
</style>
@endpush

@section('content')
<section class="inner-hero inner-hero--faith" aria-labelledby="shrines-page-title">
    <div class="inner-hero__overlay"></div>
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

<section class="shrine-calendar-page">
    <div class="figma-container">
        <header class="shrine-calendar-intro">
            <span class="shrine-calendar-intro__eyebrow">Святині Рідної Землі</span>
            <h2>Місця сили</h2>
            <p>Давні святилища, городища, могили, печери, камені, дерева та природні пам’ятки, що бережуть духовну пам’ять Роду.</p>
        </header>

        @if ($shrineCollection->isEmpty())
            <div class="shrines-empty">
                <h3>Святині ще не відкрито</h3>
                <p>Після оновлення розділу тут з’являться сторінки святинь.</p>
            </div>
        @else
            <div class="shrine-calendar-grid" aria-label="Святині Рідної Землі">
                @foreach ($shrineCollection as $shrine)
                    @php($imagePath = $imageFor($shrine))
                    <a class="shrine-photo-card" href="{{ route('faith.shrines.show', ['shrine' => $shrine['slug']]) }}">
                        @if ($imagePath)
                            <img class="shrine-photo-card__image" src="{{ $imagePath }}" alt="{{ $shrine['title'] }}">
                        @else
                            <span class="shrine-photo-card__fallback" aria-hidden="true"><span class="shrine-photo-card__sigil"></span></span>
                        @endif
                        <span class="shrine-photo-card__body">
                            <span class="shrine-photo-card__region">{{ $shrine['region'] ?? 'Святиня' }}</span>
                            <strong>{{ $shrine['title'] }}</strong>
                        </span>
                    </a>
                @endforeach
            </div>

            <nav class="shrine-region-index" aria-label="Області святинь">
                @foreach ($groupedShrines as $region => $items)
                    <a href="#shrine-region-{{ $loop->iteration }}">{{ $region }}</a>
                @endforeach
            </nav>

            <div class="shrine-region-list">
                @foreach ($groupedShrines as $region => $items)
                    <section class="shrine-region-block" id="shrine-region-{{ $loop->iteration }}" aria-labelledby="shrine-region-title-{{ $loop->iteration }}">
                        <header class="shrine-region-block__title">
                            <h3 id="shrine-region-title-{{ $loop->iteration }}">{{ $region }}</h3>
                        </header>

                        <ol class="shrine-region-block__cards" aria-label="Святині: {{ $region }}">
                            @foreach ($items as $shrine)
                                @php($imagePath = $imageFor($shrine))
                                <li>
                                    <a class="shrine-mini-card" href="{{ route('faith.shrines.show', ['shrine' => $shrine['slug']]) }}">
                                        <span class="shrine-mini-card__media">
                                            @if ($imagePath)
                                                <img src="{{ $imagePath }}" alt="{{ $shrine['title'] }}">
                                            @else
                                                <span class="shrine-mini-card__fallback" aria-hidden="true"><span></span></span>
                                            @endif
                                        </span>
                                        <span class="shrine-mini-card__body">
                                            <strong>{{ $shrine['title'] }}</strong>
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
