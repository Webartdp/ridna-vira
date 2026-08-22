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
@endphp

@push('styles')
<style>
    .shrines-list-page {
        padding: 70px 0 92px;
    }

    .shrines-list-intro {
        max-width: 760px;
        margin: 0 auto 44px;
        text-align: center;
    }

    .shrines-list-intro__eyebrow {
        display: block;
        margin-bottom: 12px;
        color: #c28b2d;
        font-family: var(--rv-font-ui);
        font-size: 14px;
        text-transform: uppercase;
    }

    .shrines-list-intro h2 {
        margin-bottom: 18px;
        font-family: var(--rv-font-display);
        font-size: clamp(32px, 5vw, 54px);
        font-weight: 700;
        line-height: 1;
        text-transform: uppercase;
    }

    .shrines-list-intro p {
        margin: 0;
        color: rgba(28, 8, 3, .78);
        font-family: var(--rv-font-ui);
        font-size: 18px;
        line-height: 1.5;
    }

    .shrines-simple-list {
        display: grid;
        gap: 14px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .shrine-list-row {
        display: grid;
        grid-template-columns: 72px minmax(0, 1fr) auto;
        gap: 20px;
        align-items: center;
        min-height: 96px;
        padding: 12px 18px 12px 12px;
        border: 3px solid #fff;
        border-radius: 20px;
        background: linear-gradient(180deg, var(--rv-paper-start) 0%, var(--rv-paper-end) 100%);
        box-shadow: var(--rv-shadow);
        transition: transform .2s ease, box-shadow .2s ease;
    }

    .shrine-list-row:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 18px rgba(28, 8, 3, .35);
    }

    .shrine-list-row__photo {
        width: 72px;
        height: 72px;
        overflow: hidden;
        border-radius: 15px;
        background: rgba(194, 139, 45, .16);
    }

    .shrine-list-row__photo img,
    .shrine-list-row__fallback {
        width: 100%;
        height: 100%;
    }

    .shrine-list-row__photo img {
        object-fit: cover;
    }

    .shrine-list-row__fallback {
        display: grid;
        place-items: center;
        background: linear-gradient(180deg, rgba(194, 139, 45, .25), rgba(255, 255, 255, .2));
    }

    .shrine-list-row__fallback span {
        width: 32px;
        height: 32px;
        border: 2px solid #c28b2d;
        border-radius: 50%;
    }

    .shrine-list-row__region {
        display: block;
        margin-bottom: 7px;
        color: rgba(64, 20, 3, .62);
        font-family: var(--rv-font-ui);
        font-size: 12px;
        text-transform: uppercase;
    }

    .shrine-list-row strong {
        display: block;
        color: #1c0803;
        font-family: var(--rv-font-display);
        font-size: 20px;
        font-weight: 700;
        line-height: 1.12;
        text-transform: uppercase;
    }

    .shrine-list-row__open {
        color: #c28b2d;
        font-family: var(--rv-font-ui);
        font-size: 13px;
        text-transform: uppercase;
        white-space: nowrap;
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

    @media (max-width: 767.98px) {
        .shrines-list-page {
            padding: 48px 0 66px;
        }

        .shrine-list-row {
            grid-template-columns: 64px minmax(0, 1fr);
            gap: 14px;
            padding: 10px;
        }

        .shrine-list-row__photo {
            width: 64px;
            height: 64px;
        }

        .shrine-list-row__open {
            grid-column: 2;
        }

        .shrine-list-row strong {
            font-size: 17px;
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

<section class="shrines-list-page">
    <div class="figma-container">
        <header class="shrines-list-intro">
            <span class="shrines-list-intro__eyebrow">Святині Рідної Землі</span>
            <h2>Місця сили</h2>
            <p>Давні святилища, городища, могили, печери, камені, дерева та природні пам’ятки, що бережуть духовну пам’ять Роду.</p>
        </header>

        @if ($shrineCollection->isEmpty())
            <div class="shrines-empty">
                <h3>Святині ще не відкрито</h3>
                <p>Після оновлення розділу тут з’являться сторінки святинь.</p>
            </div>
        @else
            <ol class="shrines-simple-list" aria-label="Список святинь">
                @foreach ($shrineCollection as $shrine)
                    @php($imagePath = $imageFor($shrine))
                    <li>
                        <a class="shrine-list-row" href="{{ route('faith.shrines.show', ['shrine' => $shrine['slug']]) }}">
                            <span class="shrine-list-row__photo" aria-hidden="true">
                                @if ($imagePath)
                                    <img src="{{ $imagePath }}" alt="">
                                @else
                                    <span class="shrine-list-row__fallback"><span></span></span>
                                @endif
                            </span>
                            <span>
                                <span class="shrine-list-row__region">{{ $shrine['region'] ?? 'Святиня' }}</span>
                                <strong>{{ $shrine['title'] }}</strong>
                            </span>
                            <span class="shrine-list-row__open">Відкрити</span>
                        </a>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</section>
@endsection
