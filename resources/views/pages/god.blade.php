@extends('layouts.app')

@section('title', $godTitle.' — Боги — Рідна Віра')
@section('description', $godTitle.' — матеріал розділу Богів Духовного центру «Рідна Віра».')

@php
    $image = (string) ($god['image'] ?? asset('assets/gods/'.$godSlug.'/portrait.svg'));
@endphp

@push('styles')
<style>
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
                <img src="{{ $image }}" alt="{{ $godTitle }}">
            </figure>

            @if ($content !== null && trim($content) !== '')
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
