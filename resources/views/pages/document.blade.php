@extends('layouts.app')

@section('title', $documentTitle.' — Рідна Віра')
@section('description', $documentTitle.' — документ Духовного центру «Рідна Віра».')

@section('content')
<section class="inner-hero inner-hero--about" aria-labelledby="document-page-title">
    <div class="inner-hero__overlay"></div>
    <div class="inner-hero__content">
        <h1 id="document-page-title">Документ</h1>
        <nav class="inner-breadcrumbs" aria-label="Навігаційний шлях">
            <a href="{{ route('home') }}">Головна</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('about.management') }}">Управління</a>
            <span aria-hidden="true">/</span>
            <span>{{ $documentTitle }}</span>
        </nav>
    </div>
</section>

<section class="document-page">
    <div class="figma-container">
        <article class="document-sheet">
            <header class="document-sheet__header">
                <div>
                    <span class="document-sheet__eyebrow">Документи Духовного центру</span>
                    <h2>{{ $documentTitle }}</h2>
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
                    <h3>Документ доступний для завантаження</h3>
                    <p>Цей матеріал збережено у форматі {{ $extension }}. Текстова версія для перегляду та редагування ще не сформована.</p>
                </div>
            @endif

            <footer class="document-sheet__footer">
                <a class="document-back-link" href="{{ route('about.management') }}#documents-center">← До каталогу документів</a>

                @if ($hasFile)
                    <a class="figma-button" href="{{ route('documents.download.file', $documentKey) }}">Завантажити {{ $extension }}</a>
                @endif
            </footer>
        </article>

        <div class="document-editor-note">
            <strong>Редагована версія</strong>
            <p>Для цього документа передбачено окремий HTML-вміст у <code>resources/content/documents/{{ $documentKey }}.html</code>. Якщо такий файл існує, сайт показує його замість імпортованої версії.</p>
        </div>
    </div>
</section>
@endsection
