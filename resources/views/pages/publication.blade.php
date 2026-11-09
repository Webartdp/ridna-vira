@extends('layouts.app')
@section('title', $entry['title'].' — Рідна Віра')
@section('description', $entry['excerpt'])
@section('body-class', 'rituals-view')
@push('styles')
    @include('partials.ritual-styles')
    @include('partials.publication-styles')
@endpush
@section('content')
<section class="article-page"><div class="figma-container">
    <nav class="article-breadcrumbs" aria-label="Навігаційний шлях"><a href="{{ route('home') }}">Головна</a><span aria-hidden="true">/</span><a href="{{ route($section === 'news' ? 'news.index' : 'creativity.index') }}">{{ $sectionTitle }}</a></nav>
    <article class="article-reader" aria-labelledby="publication-title">
        <header class="article-reader__header">
            <p class="article-reader__eyebrow">{{ $sectionTitle }}</p>
            <h1 class="ritual-page-title" id="publication-title">{{ $entry['title'] }}</h1>
            <p class="publication-meta">@if (!empty($entry['date']))<time datetime="{{ $entry['date'] }}">{{ date('d.m.Y', strtotime($entry['date'])) }}</time>@endif {{ $entry['location'] ?? $entry['author'] ?? '' }}</p>
        </header>
        <div class="article-prose publication-prose">{!! $content !!}</div>
        @if (!empty($entry['videos']))
            @if ($section === 'news')
                <section class="publication-videos" aria-label="Відео новини">
                    @foreach ($entry['videos'] as $video)
                        <iframe class="publication-video-player"
                                src="https://www.youtube-nocookie.com/embed/{{ $video }}"
                                title="{{ $entry['title'] }}{{ count($entry['videos']) > 1 ? ' — відео '.$loop->iteration : '' }}"
                                width="760" height="428" loading="lazy"
                                referrerpolicy="strict-origin-when-cross-origin"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                allowfullscreen></iframe>
                    @endforeach
                </section>
            @else
            <nav class="publication-videos" aria-label="Відеоматеріали">
                @foreach ($entry['videos'] as $video)<a href="https://www.youtube.com/watch?v={{ $video }}" target="_blank" rel="noopener noreferrer">▶ Переглянути відео{{ count($entry['videos']) > 1 ? ' '.$loop->iteration : '' }} ↗</a>@endforeach
            </nav>
            @endif
        @endif
        <footer class="article-reader__footer"><a href="{{ route($section === 'news' ? 'news.index' : 'creativity.index') }}">← {{ $section === 'news' ? 'Усі новини' : 'Уся творчість' }}</a></footer>
    </article>
</div></section>
@endsection
