@extends('layouts.app')

@section('title', $articleTitle.' — Статті — Рідна Віра')
@section('description', $articleTitle.' — локально збережений матеріал Духовного центру «Рідна Віра».')

@section('content')
<section class="inner-hero inner-hero--faith" aria-labelledby="article-page-title">
    <div class="inner-hero__overlay"></div>
    <div class="inner-hero__content">
        <h1 id="article-page-title">Статті</h1>
        <nav class="inner-breadcrumbs" aria-label="Навігаційний шлях">
            <a href="{{ route('home') }}">Головна</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('articles.index') }}">Статті</a>
            <span aria-hidden="true">/</span>
            <span>{{ $articleTitle }}</span>
        </nav>
    </div>
</section>

<section class="document-page">
    <div class="figma-container">
        <article class="document-sheet">
            <header class="document-sheet__header">
                <div>
                    <span class="document-sheet__eyebrow">Локальний архів статей</span>
                    <h2>{{ $articleTitle }}</h2>
                </div>

                <div class="document-sheet__meta">
                    <span>{{ $extension }}</span>
                    @if ($size > 0)
                        <small>{{ number_format($size / 1024, 0, ',', ' ') }} КБ</small>
                    @endif
                </div>
            </header>

            @if ($content !== null && trim($content) !== '')
                <div class="document-sheet__content">
                    {!! $content !!}
                </div>
            @else
                <div class="document-sheet__empty">
                    <h3>Матеріал доступний для завантаження</h3>
                    <p>Цей матеріал збережено у форматі {{ $extension }}. Його можна відкрити або зберегти як локальний файл з нашого сайту.</p>
                </div>
            @endif

            <footer class="document-sheet__footer">
                <a class="document-back-link" href="{{ route('articles.index') }}">← До списку статей</a>

                @if ($hasFile)
                    <a class="figma-button" href="{{ route('articles.download', ['article' => $articleSlug]) }}">Завантажити {{ $extension }}</a>
                @endif
            </footer>
        </article>
    </div>
</section>
@endsection
