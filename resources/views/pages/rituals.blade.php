@extends('layouts.app')
@section('title', 'Обряди — Рідна Віра')
@section('description', 'Обряди Рідної Віри: богославлення, посвячення, вінчання, свята Кола Сварожого, контакти духовних провідників і література.')
@section('body-class', 'rituals-view')
@push('styles')
    @include('partials.ritual-styles')
@endpush

@section('content')
<section class="inner-hero inner-hero--faith" aria-labelledby="rituals-page-title">
    <div class="inner-hero__overlay"></div>
    <div class="inner-hero__content">
        <h1 id="rituals-page-title">Обряди</h1>
        <nav class="inner-breadcrumbs" aria-label="Навігаційний шлях">
            <a href="{{ route('home') }}">Головна</a><span aria-hidden="true">/</span>
            <a href="{{ route('faith') }}">Рідна Віра</a><span aria-hidden="true">/</span><span>Обряди</span>
        </nav>
    </div>
</section>
<section class="articles-page">
    <div class="figma-container">
        <div class="rituals-intro">
            <p>Обряди єднають людину з Родом, Рідними Богами, Предками та природним колом життя. Тут зібрано описи святодій, порядок проведення обрядів, матеріали для підготовки й дослідження народних звичаїв.</p>
            <p>Для проведення обряду або лекції з рідновір’я можна звернутися до духовних провідників.</p>
        </div>
        <nav class="rituals-nav" aria-label="Зміст розділу">
        @foreach ($groups as $group)
                <a href="#{{ $group['id'] }}">{{ $group['title'] }}</a>
            @endforeach
            <a href="#guides">Духовні провідники</a><a href="#videos">Відео</a>
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
                            <h3><a href="{{ route('faith.rituals.show', $slug) }}">{{ $entry['title'] }}</a></h3>
                            @if (!empty($entry['summary']))<p>{{ $entry['summary'] }}</p>@endif
                            <a class="article-preview__link" href="{{ route('faith.rituals.show', $slug) }}">{{ $entry['category'] === 'literature' ? 'Відкрити матеріал' : 'Читати обряд' }} <span aria-hidden="true">→</span></a>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
        <!-- /cms-live:catalogue -->
        <section class="rituals-section" id="guides" aria-labelledby="heading-guides">
            <h2 id="heading-guides">Духовні провідники</h2>
            <div class="ritual-guides">
                @foreach ($guides as $guide)
                    <article class="ritual-guide">
                        <p class="ritual-guide__city">{{ $guide['city'] }}</p>
                        <h3>{{ $guide['name'] }}</h3>
                        @foreach ($guide['phones'] as $phone)
                            <a href="tel:+38{{ $phone }}">{{ substr($phone, 0, 3) }} {{ substr($phone, 3, 3) }} {{ substr($phone, 6, 2) }} {{ substr($phone, 8, 2) }}</a>
                        @endforeach
                        @foreach ($guide['emails'] as $email)<a href="mailto:{{ $email }}">{{ $email }}</a>@endforeach
                        @foreach ($guide['socials'] as $social)<a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer">{{ $social['title'] }} ↗</a>@endforeach
                    </article>
                @endforeach
            </div>
        </section>
        <section class="rituals-section" id="videos" aria-labelledby="heading-videos">
            <h2 id="heading-videos">Відеоматеріали</h2>
            <ul class="ritual-videos">
                @foreach ($videos as $video)
                    <li><a href="https://www.youtube.com/watch?v={{ $video['id'] }}" target="_blank" rel="noopener noreferrer"><span class="ritual-video-icon" aria-hidden="true">▶</span><span>{{ $video['title'] }} <span aria-hidden="true">↗</span></span></a></li>
                @endforeach
            </ul>
        </section>
        <p class="ritual-source">Матеріали розділу перенесено з <a href="{{ $source }}" target="_blank" rel="noopener noreferrer">svit.in.ua</a>.</p>
    </div>
</section>
@endsection
