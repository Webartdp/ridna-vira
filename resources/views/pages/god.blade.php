@extends('layouts.app')

@section('title', $godTitle.' — Боги — Рідна Віра')
@section('body-class', 'gods-view')
@section('description', $description['summary'] ?? ($godTitle.' — Рідна Віра'))

@push('styles')
<style>
    body.gods-view { background: #f3efe3; }
    body.gods-view::before { content: none; }

    .god-reading-page {
        padding: 72px 0 94px;
    }

    .god-reading-article {
        max-width: 920px;
        margin: 0 auto;
    }

    .god-reading-figure {
        max-width: 360px;
        margin: 0 auto 38px;
        overflow: hidden;
        border: 3px solid #fff;
        border-radius: 8px;
        background: #2f3d2e;
        box-shadow: var(--rv-shadow);
    }

    .god-reading-figure img {
        display: block;
        width: 100%;
        height: auto;
    }

    .god-reading-content {
        color: #1c0803;
        font-family: var(--rv-font-ui);
        font-size: 18px;
        line-height: 1.72;
        overflow-wrap: anywhere;
    }

    .god-reading-lead {
        font-size: 1.15em;
        line-height: 1.65;
    }

    .god-reading-sources {
        margin-top: 40px;
        padding-top: 24px;
        border-top: 1px solid rgba(64, 20, 3, .16);
    }

    .god-reading-sources p,
    .god-reading-sources li {
        font-size: 14px;
        line-height: 1.65;
    }

    .god-reading-content > *:first-child {
        margin-top: 0;
    }

    .god-reading-content p,
    .god-reading-content li {
        margin-bottom: 18px;
    }

    .god-reading-content h1,
    .god-reading-content h2,
    .god-reading-content h3,
    .god-reading-content h4 {
        margin: 34px 0 16px;
        font-family: var(--rv-font-display);
        font-weight: 700;
        line-height: 1.14;
        text-transform: uppercase;
    }

    .god-reading-content h1,
    .god-reading-content h2 {
        font-size: 30px;
    }

    .god-reading-content h3 {
        font-size: 24px;
    }

    .god-reading-content a {
        color: #8a5d16;
        border-bottom: 1px solid rgba(138, 93, 22, .34);
    }

    .god-reading-content img {
        max-height: 560px;
        width: auto;
        max-width: 100%;
        margin: 28px auto;
        border: 3px solid #fff;
        border-radius: 8px;
        box-shadow: var(--rv-shadow);
        object-fit: contain;
    }

    .god-reading-content blockquote {
        margin: 0 0 18px;
        padding: 0;
        border: 0;
        background: transparent;
    }

    .god-reading-empty {
        padding: 40px 0;
        border-top: 1px solid rgba(64, 20, 3, .16);
        border-bottom: 1px solid rgba(64, 20, 3, .16);
    }

    .god-reading-empty h2 {
        font-family: var(--rv-font-display);
        font-size: 28px;
        text-transform: uppercase;
    }

    .god-reading-empty p {
        max-width: 560px;
        color: rgba(64, 20, 3, .7);
        font-family: var(--rv-font-ui);
        font-size: 16px;
        line-height: 1.5;
    }

    .god-reading-footer {
        max-width: 920px;
        margin: 42px auto 0;
    }

    @media (max-width: 767.98px) {
        .god-reading-page {
            padding: 50px 0 68px;
        }

        .god-reading-content {
            font-size: 16px;
        }
    }
</style>
@endpush

@section('content')
<section class="inner-hero inner-hero--faith" aria-labelledby="god-page-title">
    <div class="inner-hero__overlay"></div>
    <div class="inner-hero__content">
        <h1 id="god-page-title">{{ $godTitle }}</h1>
        <nav class="inner-breadcrumbs" aria-label="Навігаційний шлях">
            <a href="{{ route('home') }}">Головна</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('faith') }}">Рідна Віра</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('faith.gods') }}">Боги</a>
            <span aria-hidden="true">/</span>
            <span>{{ $godTitle }}</span>
        </nav>
    </div>
</section>

<section class="god-reading-page">
    <div class="figma-container">
        <article class="god-reading-article">
            <figure class="god-reading-figure">
                @include('partials.god-image', [
                    'slug' => $godSlug,
                    'sizes' => '(min-width: 392px) 354px, calc(100vw - 38px)',
                    'imageAlt' => $godTitle,
                    'imageLoading' => 'eager',
                    'imagePriority' => 'high',
                ])
            </figure>

            @if ($description !== null)
                <div class="god-reading-content">
                    <p class="god-reading-lead">{{ $description['lead'] }}</p>
                    @foreach ($description['sections'] as $section)
                        <section>
                            <h2>{{ $section['title'] }}</h2>
                            @foreach ($section['paragraphs'] as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                        </section>
                    @endforeach
                    <aside class="god-reading-sources" aria-labelledby="god-sources-title">
                        <h2 id="god-sources-title">Джерела опису</h2>
                        <p>Редакційний виклад за наведеними матеріалами. Авторські тлумачення й відмінності між традиціями позначено в тексті.</p>
                        <ul>
                            @foreach ($description['sources'] as $source)
                                <li><a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer">{{ $source['title'] }} <span aria-hidden="true">↗</span></a></li>
                            @endforeach
                        </ul>
                    </aside>
                </div>
            @elseif ($content !== null && trim($content) !== '')
                <div class="god-reading-content">
                    {!! $content !!}
                </div>
            @else
                <div class="god-reading-empty">
                    <h2>Матеріал очікує імпорту</h2>
                    <p>Після перенесення старого розділу тут з’явиться повний текст.</p>
                </div>
            @endif
        </article>

        <footer class="god-reading-footer">
            <a class="document-back-link" href="{{ route('faith.gods') }}">← До списку Богів</a>
        </footer>
    </div>
</section>
@endsection
