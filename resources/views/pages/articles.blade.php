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
        <div id="articles-catalogue">
            <form class="articles-search" action="{{ route('articles.index') }}#articles-catalogue" method="get" role="search" aria-label="Пошук статей">
                <label for="article-title-search">
                    Назва статті
                    <input id="article-title-search" type="search" name="q" value="{{ $filters['q'] }}" maxlength="255" placeholder="Введіть назву або її частину">
                </label>
                <label for="article-author-search">
                    Автор
                    <input id="article-author-search" type="search" name="author" value="{{ $filters['author'] }}" maxlength="255" placeholder="Введіть ім’я або прізвище">
                </label>
                <button class="articles-search__submit" type="submit">Знайти</button>
                @if ($isFiltered)
                    <a href="{{ route('articles.index') }}#articles-catalogue" class="articles-search__reset">Скинути пошук</a>
                @endif
            </form>

            @forelse ($groups as $author => $entries)
                <section class="articles-group" aria-labelledby="article-author-{{ $loop->index }}">
                    <h3 id="article-author-{{ $loop->index }}">{{ $author === '' ? 'Автор не вказаний' : $author }}</h3>
                    <div class="articles-list">
                        @foreach ($entries as $article)
                            <article class="article-preview">
                                <h4><a href="{{ route('articles.show', $article['slug']) }}">{{ $article['title'] }}</a></h4>
                                @if ($article['excerpt'] !== '')
                                    <p>{{ $article['excerpt'] }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @empty
                <p class="articles-empty">{{ $isFiltered ? 'За цим запитом статей не знайдено.' : 'На цій сторінці немає статей.' }}</p>
            @endforelse
        </div>
        @if ($articles->hasPages())
            {{ $articles->links('partials.articles-pagination') }}
        @endif
        <!-- /cms-live:catalogue -->
    </div>
</section>
@endsection
