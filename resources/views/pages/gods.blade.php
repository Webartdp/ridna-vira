@extends('layouts.app')

@section('title', 'Боги — Рідна Віра')
@section('description', 'Розділ Рідні Боги: перенесені матеріали, образи та описи Богів Рідної Віри.')

@php
    $imageFor = static function (array $god): string {
        $image = (string) ($god['image'] ?? '');
        $slug = (string) ($god['slug'] ?? 'bog');

        return $image !== '' ? $image : asset('assets/gods/'.$slug.'/portrait.svg');
    };
@endphp

@push('styles')
<style>
    .gods-page {
        padding: 70px 0 94px;
    }

    .gods-intro {
        max-width: 780px;
        margin: 0 auto 42px;
        text-align: center;
    }

    .gods-intro__eyebrow {
        display: block;
        margin-bottom: 12px;
        color: #8a5d16;
        font-family: var(--rv-font-ui);
        font-size: 14px;
        text-transform: uppercase;
    }

    .gods-intro h2 {
        margin: 0;
        font-family: var(--rv-font-display);
        font-size: clamp(34px, 5vw, 56px);
        line-height: 1;
        text-transform: uppercase;
    }

    .gods-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .god-card {
        display: grid;
        min-height: 100%;
        overflow: hidden;
        border: 3px solid #fff;
        border-radius: 8px;
        background: linear-gradient(180deg, var(--rv-paper-start) 0%, var(--rv-paper-end) 100%);
        box-shadow: var(--rv-shadow);
        transition: transform .2s ease, box-shadow .2s ease;
    }

    .god-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 22px rgba(28, 8, 3, .32);
    }

    .god-card__image {
        aspect-ratio: 4 / 5;
        background: #2f3d2e;
    }

    .god-card__image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .god-card__body {
        display: grid;
        gap: 8px;
        padding: 16px 16px 18px;
    }

    .god-card strong {
        color: #1c0803;
        font-family: var(--rv-font-display);
        font-size: 22px;
        line-height: 1.05;
        text-transform: uppercase;
    }

    .god-card span {
        color: #8a5d16;
        font-family: var(--rv-font-ui);
        font-size: 12px;
        text-transform: uppercase;
    }

    .gods-empty {
        padding: 44px 0;
        border-top: 1px solid rgba(64, 20, 3, .14);
        border-bottom: 1px solid rgba(64, 20, 3, .14);
        text-align: center;
    }

    .gods-footer {
        margin-top: 46px;
    }

    @media (max-width: 1199.98px) {
        .gods-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .gods-page {
            padding: 50px 0 70px;
        }

        .gods-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .god-card__body {
            padding: 13px;
        }

        .god-card strong {
            font-size: 18px;
        }
    }
</style>
@endpush

@section('content')
<section class="inner-hero inner-hero--faith" aria-labelledby="gods-page-title">
    <div class="inner-hero__overlay"></div>
    <div class="inner-hero__content">
        <h1 id="gods-page-title">Боги</h1>
        <nav class="inner-breadcrumbs" aria-label="Навігаційний шлях">
            <a href="{{ route('home') }}">Головна</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('faith') }}">Рідна Віра</a>
            <span aria-hidden="true">/</span>
            <span>Боги</span>
        </nav>
    </div>
</section>

<section class="gods-page">
    <div class="figma-container">
        <header class="gods-intro">
            <span class="gods-intro__eyebrow">Рідні Боги</span>
            <h2>Пантеон Рідної Віри</h2>
        </header>

        @if (empty($gods))
            <div class="gods-empty">
                <p>Матеріали розділу буде відкрито після імпорту.</p>
            </div>
        @else
            <ol class="gods-grid" aria-label="Список Богів">
                @foreach ($gods as $god)
                    @php($slug = (string) ($god['slug'] ?? Str::slug($god['title'] ?? 'bog')))
                    <li>
                        <a class="god-card" href="{{ route('faith.gods.show', ['god' => $slug]) }}">
                            <span class="god-card__image" aria-hidden="true">
                                <img src="{{ $imageFor($god) }}" alt="">
                            </span>
                            <span class="god-card__body">
                                <strong>{{ $god['title'] ?? 'Бог' }}</strong>
                                <span>Відкрити</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ol>
        @endif

        <footer class="gods-footer">
            <a class="document-back-link" href="{{ route('faith') }}">← До розділу «Рідна Віра»</a>
        </footer>
    </div>
</section>
@endsection
