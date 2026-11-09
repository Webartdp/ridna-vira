@extends('layouts.app')

@section('title', 'Статті — Рідна Віра')
@section('description', 'Статті та дослідження про український світогляд, духовну спадщину й народну традицію.')

@push('styles')
    @vite('resources/scss/articles.scss')
@endpush

@section('content')
<section class="inner-hero inner-hero--faith" aria-labelledby="articles-page-title">
    <div class="inner-hero__overlay"></div>
    <div class="inner-hero__content">
        <h1 id="articles-page-title">Статті</h1>
        <nav class="inner-breadcrumbs" aria-label="Навігаційний шлях">
            <a href="{{ route('home') }}">Головна</a>
            <span aria-hidden="true">/</span><span>Статті</span>
        </nav>
    </div>
</section>

<section class="articles-page">
    <div class="figma-container">
        <header class="articles-intro">
            <h2>Пізнаємо свою спадщину</h2>
            <p>Світогляд, історія та жива традиція — у статтях і дослідженнях.</p>
        </header>
        <!-- cms-live:catalogue -->
        <div class="articles-list" aria-label="Статті та дослідження">
            @forelse ($articles as $article)
                <article class="article-preview">
                    <h3><a href="{{ route('articles.show', $article['slug']) }}">{{ $article['title'] }}</a></h3>
                    <p>{{ $article['excerpt'] }}</p>
                    <a class="article-preview__link" href="{{ route('articles.show', $article['slug']) }}">
                        {{ strtolower($article['extension'] ?? '') === 'html' ? 'Читати статтю' : 'Відкрити матеріал' }}
                        <span aria-hidden="true">→</span>
                        <span class="visually-hidden">: {{ $article['title'] }}</span>
                    </a>
                </article>
            @empty
                <p class="articles-empty">Статті готуються до публікації. Завітайте трохи згодом.</p>
            @endforelse
        </div>
        @if ($articles->hasPages())
            {{ $articles->links('partials.articles-pagination') }}
        @endif
        <!-- /cms-live:catalogue -->
    </div>
</section>
@endsection
