@extends('layouts.app')
@section('title', $entry['title'].' — Рідна Віра')
@section('description', $entry['summary'])
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
            <a href="{{ route('faith.prayers') }}">Молитви</a>
        </nav>
        <article class="article-reader" aria-labelledby="prayer-page-title">
            <header class="article-reader__header">
                <p class="article-reader__eyebrow">{{ $group }}</p>
                <h1 class="ritual-page-title" id="prayer-page-title">{{ $entry['title'] }}</h1>
                @if (!empty($entry['author']))<p>{{ $entry['author'] }}</p>@endif
            </header>
            @if (!empty($entry['files']))
                <nav class="ritual-downloads" aria-label="Завантажити видання">
                    @foreach ($entry['files'] as $file)
                        <a href="{{ route('faith.prayers.download', [$slug, $file['format']]) }}">Завантажити {{ strtoupper($file['format']) }} <small>{{ number_format($file['bytes'] / 1024 / 1024, 1, ',', ' ') }} МБ</small></a>
                    @endforeach
                </nav>
            @endif
            @if (count($entry['toc'] ?? []) >= 3)
                <details class="ritual-toc">
                    <summary>Зміст добірки</summary>
                    <ol>@foreach ($entry['toc'] as $heading)<li><a href="#{{ $heading['anchor'] }}">{{ $heading['title'] }}</a></li>@endforeach</ol>
                </details>
            @endif
            @if ($reading)
                <div class="article-prose">
                    @foreach ($reading as $block)
                        @if ($block['type'] === 'heading')
                            <h2 id="{{ $block['id'] }}">{{ $block['title'] }}</h2>
                        @elseif ($block['type'] === 'prose')
                            @if (preg_match('/^<(ul|ol|table|div|figure|pre)\b/', $block['html'])){!! $block['html'] !!}@else<p>{!! $block['html'] !!}</p>@endif
                        @else
                            @php($label = ['prayer' => 'Молитва', 'praise' => 'Славлення', 'spell' => 'Замовляння · текст видання', 'direction' => 'Обрядові дії', 'note' => 'Примітка'][$block['type']])
                            <div class="ritual-passage ritual-passage--{{ $block['type'] }}" role="group" aria-label="{{ $label }}">
                                <p class="ritual-passage__label">{{ $label }}</p>
                                @foreach ($block['paragraphs'] as $paragraph)<p>{!! $paragraph !!}</p>@endforeach
                            </div>
                        @endif
                    @endforeach
                </div>
            @else
                <div class="article-attachment"><p>Завантажте молитовник у PDF, щоб читати його повністю та зберегти для себе.</p></div>
            @endif
            @if (!empty($entry['audio']))
                <section class="prayer-audio" aria-labelledby="prayer-audio-title">
                    <h2 id="prayer-audio-title">Аудіозаписи</h2>
                    @foreach ($entry['audio'] as $track)
                        <div class="prayer-audio__track">
                            <h3 id="audio-track-{{ $loop->index }}">{{ $track['title'] }}</h3>
                            <audio controls preload="none" aria-labelledby="audio-track-{{ $loop->index }}"><source src="{{ asset('assets/prayers/'.$track['path']) }}" type="audio/mpeg"><a href="{{ asset('assets/prayers/'.$track['path']) }}">Відкрити аудіозапис</a></audio>
                        </div>
                    @endforeach
                </section>
            @endif
            @if (!empty($entry['videos']))
                <section class="rituals-section" aria-labelledby="prayer-videos-title">
                    <h2 id="prayer-videos-title">Відеомолитви</h2>
                    @include('partials.prayer-videos', ['videos' => $entry['videos']])
                </section>
            @endif
            <p class="ritual-source">Джерело: <a href="https://www.svit.in.ua/{{ $entry['source'] }}" target="_blank" rel="noopener noreferrer">svit.in.ua</a>.</p>
            <footer class="article-reader__footer"><a href="{{ route('faith.prayers') }}#{{ $entry['category'] }}">← До розділу «Молитви»</a></footer>
        </article>
    </div>
</section>
@endsection
