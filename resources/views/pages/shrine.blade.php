@extends('layouts.app')

@section('title', $shrineTitle.' — Святині — Рідна Віра')
@section('description', $shrineTitle.' — локально збережений матеріал розділу святинь Духовного центру «Рідна Віра».')

@section('content')
<section class="inner-hero inner-hero--faith" aria-labelledby="shrine-page-title">
    <div class="inner-hero__overlay"></div>
    <div class="inner-hero__content">
        <h1 id="shrine-page-title">Святині</h1>
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

<section class="document-page">
    <div class="figma-container">
        <article class="document-sheet">
            <header class="document-sheet__header">
                <div>
                    <span class="document-sheet__eyebrow">{{ $shrine['region'] ?? 'Святиня' }}</span>
                    <h2>{{ $shrineTitle }}</h2>
                </div>

                <div class="document-sheet__meta">
                    <span>HTML</span>
                    @if (!empty($shrine['images']))
                        <small>{{ $shrine['images'] }} зобр.</small>
                    @endif
                </div>
            </header>

            @if ($content !== null && trim($content) !== '')
                <div class="document-sheet__content">
                    {!! $content !!}
                </div>
            @else
                <div class="document-sheet__empty">
                    <h3>Матеріал ще не імпортовано</h3>
                    <p>Після перенесення зі старого сайту тут буде повний локальний текст святині.</p>
                </div>
            @endif

            <footer class="document-sheet__footer">
                <a class="document-back-link" href="{{ route('faith.shrines') }}">← До списку святинь</a>
            </footer>
        </article>
    </div>
</section>
@endsection
