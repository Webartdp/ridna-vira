@extends('layouts.app')
@section('title', $entry['title'].' — Обряди — Рідна Віра')
@section('description', $entry['summary'] ?: $entry['title'].' — матеріали розділу обрядів Рідної Віри.')
@section('body-class', 'rituals-view')
@push('styles')
    @include('partials.ritual-styles')
@endpush

@section('content')
<section class="article-page">
    <div class="figma-container">
        <nav class="article-breadcrumbs" aria-label="Навігаційний шлях">
            <a href="{{ route('home') }}">Головна</a><span aria-hidden="true">/</span>
            <a href="{{ route('faith') }}">Рідна Віра</a><span aria-hidden="true">/</span>
            <a href="{{ route('faith.rituals') }}">Обряди</a>
        </nav>
        <article class="article-reader" aria-labelledby="ritual-page-title">
            <header class="article-reader__header">
                <p class="article-reader__eyebrow">{{ $group }}</p>
                <h1 class="ritual-page-title" id="ritual-page-title">{{ $entry['title'] }}</h1>
                @if (!empty($entry['author']))<p>{{ $entry['author'] }}</p>@endif
            </header>
            @if (!empty($entry['files']))
                <nav class="ritual-downloads" aria-label="Завантажити матеріал">
                    @foreach ($entry['files'] as $file)
                        <a href="{{ route('faith.rituals.download', [$slug, $file['format']]) }}">Завантажити {{ strtoupper($file['format']) }} <small>{{ number_format($file['bytes'] / 1024 / 1024, 1, ',', ' ') }} МБ</small></a>
                    @endforeach
                </nav>
            @endif
            @if (count($entry['toc'] ?? []) >= 3)
                <details class="ritual-toc">
                    <summary>Зміст матеріалу</summary>
                    <ol>@foreach ($entry['toc'] as $heading)<li><a href="#{{ $heading['anchor'] }}">{{ $heading['title'] }}</a></li>@endforeach</ol>
                </details>
            @endif
            @if ($content)
                @if ($entry['category'] !== 'literature')
                    <div class="ritual-reading-key" aria-label="Позначення в тексті">
                        <span><i aria-hidden="true"></i>Молитви та славлення</span>
                        <span><i class="ritual-key-song" aria-hidden="true"></i>Пісні</span>
                        <span><i class="ritual-key-direction" aria-hidden="true"></i>Обрядові дії</span>
                    </div>
                @endif
                <div class="article-prose">{!! $content !!}</div>
            @else
                <div class="article-attachment"><p>Повний текст видання доступний у документі. Завантажте його для читання та збереження.</p></div>
            @endif
            <p class="ritual-source">Джерело: <a href="https://www.svit.in.ua/{{ $entry['source'] }}" target="_blank" rel="noopener noreferrer">svit.in.ua</a>. Авторство матеріалу збережено.</p>
            <footer class="article-reader__footer"><a href="{{ route('faith.rituals') }}#{{ $entry['category'] }}">← До розділу «Обряди»</a></footer>
        </article>
    </div>
</section>
@endsection
