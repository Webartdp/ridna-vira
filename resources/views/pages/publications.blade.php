@extends('layouts.app')
@section('title', $title.' — Рідна Віра')
@section('description', $section === 'news' ? 'Новини громад і Духовного центру «Рідна Віра»: події, свята та життя спільноти.' : 'Живопис, поезія, народне мистецтво та авторські роботи Рідної Віри.')
@section('body-class', 'rituals-view')
@push('styles')
    @include('partials.ritual-styles')
    @include('partials.publication-styles')
@endpush
@section('content')
<section class="inner-hero inner-hero--faith" aria-labelledby="publications-title">
    <div class="inner-hero__overlay"></div>
    <div class="inner-hero__content">
        <h1 id="publications-title">{{ $title }}</h1>
        <nav class="inner-breadcrumbs" aria-label="Навігаційний шлях"><a href="{{ route('home') }}">Головна</a><span aria-hidden="true">/</span><span>{{ $title }}</span></nav>
    </div>
</section>
<section class="articles-page"><div class="figma-container">
    <!-- cms-live:catalogue -->
    @if ($section === 'creativity')
        <nav class="publication-categories" aria-label="Напрями творчості">
            <a href="{{ route('creativity.index') }}" @class(['is-current' => $category === ''])>Усі напрями</a>
            @foreach ($categories as $label)<a href="{{ route('creativity.index', ['category' => $label]) }}" @class(['is-current' => $category === $label])>{{ $label }}</a>@endforeach
        </nav>
    @endif
    <div class="publication-cards">
        @foreach ($entries as $entry)
            @php($entryUrl = route($section === 'news' ? 'news.show' : 'creativity.show', $entry['slug']))
            <article class="publication-card">
                <a class="publication-card__visual" href="{{ $entryUrl }}" tabindex="-1" aria-hidden="true">@include('partials.publication-image', ['visual' => $entry['image'] ?? null, 'placeholder' => $title])</a>
                <div class="publication-card__body">
                    @if (!empty($entry['date']))<time class="material-meta" datetime="{{ $entry['date'] }}">{{ date('d.m.Y', strtotime($entry['date'])) }}{{ !empty($entry['location']) ? ' · '.$entry['location'] : '' }}</time>@elseif (!empty($entry['category']))<span class="material-meta">{{ $entry['category'] }}</span>@endif
                    <h2><a href="{{ $entryUrl }}">{{ $entry['title'] }}</a></h2>
                    <p>{{ $entry['excerpt'] }}</p>
                    <a class="publication-card__link" href="{{ $entryUrl }}">Читати <span aria-hidden="true">→</span><span class="visually-hidden">: {{ $entry['title'] }}</span></a>
                </div>
            </article>
        @endforeach
    </div>
    {{ $entries->links('partials.articles-pagination', ['paginationLabel' => 'Сторінки розділу «'.$title.'»']) }}
<!-- /cms-live:catalogue -->
</div></section>
@endsection
