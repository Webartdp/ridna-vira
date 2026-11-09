@extends('layouts.app')

@section('title', $articleTitle.' — Рідна Віра')
@section('description', $articleTitle.' — статті та дослідження Духовного центру «Рідна Віра».')

@push('styles')
    @vite('resources/scss/articles.scss')
@endpush

@section('content')
<section class="article-page">
    <div class="figma-container">
        <nav class="article-breadcrumbs" aria-label="Навігаційний шлях">
            <a href="{{ route('home') }}">Головна</a><span aria-hidden="true">/</span>
            <a href="{{ route('articles.index') }}">Статті</a>
        </nav>
        <article class="article-reader" aria-labelledby="article-page-title">
            <header class="article-reader__header">
                <p class="article-reader__eyebrow">Статті та дослідження</p>
                <h1 id="article-page-title">{{ $articleTitle }}</h1>
            </header>
            @if ($content !== null && trim($content) !== '')
                <div class="article-prose">{!! $content !!}</div>
            @else
                <div class="article-attachment">
                    <p>Відкрийте або збережіть матеріал, щоб прочитати його повністю.</p>
                    @if ($hasFile)
                        <a class="figma-button" href="{{ route('articles.download', $articleSlug) }}">Завантажити {{ $extension }}</a>
                    @endif
                </div>
            @endif
            <footer class="article-reader__footer">
                <a href="{{ route('articles.index') }}">← До всіх статей</a>
            </footer>
        </article>
    </div>
</section>
@endsection
