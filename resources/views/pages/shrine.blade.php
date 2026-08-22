@extends('layouts.app')

@section('title', $shrineTitle.' — Святині — Рідна Віра')
@section('description', $shrineTitle.' — матеріал розділу святинь Духовного центру «Рідна Віра».')

@push('styles')
<style>
    .shrine-reading-page {
        padding: 72px 0 94px;
    }

    .shrine-reading-article {
        max-width: 920px;
        margin: 0 auto;
    }

    .shrine-reading-content {
        color: #1c0803;
        font-family: var(--rv-font-ui);
        font-size: 18px;
        line-height: 1.72;
    }

    .shrine-reading-content > *:first-child {
        margin-top: 0;
    }

    .shrine-reading-content p,
    .shrine-reading-content li {
        margin-bottom: 18px;
    }

    .shrine-reading-content h1,
    .shrine-reading-content h2,
    .shrine-reading-content h3,
    .shrine-reading-content h4 {
        margin: 34px 0 16px;
        font-family: var(--rv-font-display);
        font-weight: 700;
        line-height: 1.14;
        text-transform: uppercase;
    }

    .shrine-reading-content h1,
    .shrine-reading-content h2 {
        font-size: 30px;
    }

    .shrine-reading-content h3 {
        font-size: 24px;
    }

    .shrine-reading-content a {
        color: #8a5d16;
        border-bottom: 1px solid rgba(138, 93, 22, .34);
    }

    .shrine-reading-content img {
        max-height: 560px;
        width: auto;
        max-width: 100%;
        margin: 28px auto;
        border: 3px solid #fff;
        border-radius: 8px;
        box-shadow: var(--rv-shadow);
        object-fit: contain;
    }

    .shrine-reading-content blockquote {
        margin: 28px 0;
        padding: 22px 26px;
        border-left: 6px solid #35533b;
        background: rgba(255, 255, 255, .54);
    }

    .shrine-reading-empty {
        padding: 40px 0;
        border-top: 1px solid rgba(64, 20, 3, .16);
        border-bottom: 1px solid rgba(64, 20, 3, .16);
    }

    .shrine-reading-empty h2 {
        font-family: var(--rv-font-display);
        font-size: 28px;
        text-transform: uppercase;
    }

    .shrine-reading-empty p {
        max-width: 560px;
        color: rgba(64, 20, 3, .7);
        font-family: var(--rv-font-ui);
        font-size: 16px;
        line-height: 1.5;
    }

    .shrine-reading-footer {
        max-width: 920px;
        margin: 42px auto 0;
    }

    @media (max-width: 767.98px) {
        .shrine-reading-page {
            padding: 50px 0 68px;
        }

        .shrine-reading-content {
            font-size: 16px;
        }
    }
</style>
@endpush

@section('content')
<section class="inner-hero inner-hero--faith" aria-labelledby="shrine-page-title">
    <div class="inner-hero__overlay"></div>
    <div class="inner-hero__content">
        <h1 id="shrine-page-title">{{ $shrineTitle }}</h1>
        <nav class="inner-breadcrumbs" aria-label="Навігаційний шлях">
            <a href="{{ route('home') }}">Головна</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('faith') }}">Рідна Віра</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('faith.shrines') }}">Святині</a>
            <span aria-hidden="true">/</span>
            <span>{{ $shrineTitle }}</span>
        </nav>
    </div>
</section>

<section class="shrine-reading-page">
    <div class="figma-container">
        <article class="shrine-reading-article">
            @if ($content !== null && trim($content) !== '')
                <div class="shrine-reading-content">
                    {!! $content !!}
                </div>
            @else
                <div class="shrine-reading-empty">
                    <h2>Матеріал тимчасово недоступний</h2>
                    <p>Сторінка святині буде відкрита після оновлення розділу.</p>
                </div>
            @endif
        </article>

        <footer class="shrine-reading-footer">
            <a class="document-back-link" href="{{ route('faith.shrines') }}">← До списку святинь</a>
        </footer>
    </div>
</section>
@endsection
