@extends('layouts.app')
@section('title', 'Молитви — Рідна Віра')
@section('description', 'Тематичні та авторські молитви Рідної Віри, славлення, молитовник, відео та дослідження народних замовлянь.')
@section('body-class', 'rituals-view')
@push('styles')
    @include('partials.ritual-styles')
@endpush

@section('content')
<section class="inner-hero inner-hero--faith" aria-labelledby="prayers-page-title">
    <div class="inner-hero__overlay"></div>
    <div class="inner-hero__content">
        <h1 id="prayers-page-title">Молитви</h1>
        <nav class="inner-breadcrumbs" aria-label="Навігаційний шлях">
            <a href="{{ route('home') }}">Головна</a><span aria-hidden="true">/</span>
            <a href="{{ route('faith') }}">Рідна Віра</a><span aria-hidden="true">/</span><span>Молитви</span>
        </nav>
    </div>
</section>
<section class="articles-page">
    <div class="figma-container">
        <div class="rituals-intro">
            <p>Молитва — це звернення до Рідних Богів, подяка за життя, прохання про підтримку й духовне єднання з Родом. Тут зібрано молитви за темами, авторські добірки, славлення та народні замовляння.</p>
            <p>Оберіть тему або автора. Повні тексти можна читати на сайті, а молитовник — завантажити для збереження.</p>
        </div>
        <nav class="rituals-nav" aria-label="Зміст розділу">
        @foreach ($groups as $group)<a href="#{{ $group['id'] }}">{{ $group['title'] }}</a>@endforeach
            <a href="#videos">Відеомолитви</a>
        </nav>
        <!-- cms-live:catalogue -->
        @foreach ($groups as $group)
            <section class="rituals-section" id="{{ $group['id'] }}" aria-labelledby="heading-{{ $group['id'] }}">
                <h2 id="heading-{{ $group['id'] }}">{{ $group['title'] }}</h2>
                <div class="articles-list">
                    @foreach ($entries as $slug => $entry)
                        @continue($entry['category'] !== $group['id'])
                        <article class="article-preview">
                            @if (!empty($entry['author']))<p class="article-preview__author">{{ $entry['author'] }}</p>@endif
                            <h3><a href="{{ route('faith.prayers.show', $slug) }}">{{ $entry['title'] }}</a></h3>
                            <p>{{ $entry['summary'] }}</p>
                            <a class="article-preview__link" href="{{ route('faith.prayers.show', $slug) }}">{{ $entry['category'] === 'books' ? 'Відкрити молитовник' : 'Читати' }} <span aria-hidden="true">→</span></a>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
        <!-- /cms-live:catalogue -->
        <section class="rituals-section" id="videos" aria-labelledby="heading-videos">
            <h2 id="heading-videos">Відеомолитви</h2>
            @include('partials.prayer-videos', ['videos' => $videos])
        </section>
    </div>
</section>
@endsection
