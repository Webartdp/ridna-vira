@extends('layouts.app')

@section('title', 'Рідна Віра — Духовний центр')
@section('description', 'Офіційний сайт Духовного центру «Рідна Віра»: громади, свята, обряди, новини, статті та творчість.')

@push('styles')
    @include('partials.publication-styles')
    <style>
        @media (max-width: 479.98px) {
            .calendar-stage { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .calendar-stage .holiday-card { min-width: 0; }
            .calendar-stage .holiday-card strong { max-width: 100%; overflow-wrap: anywhere; }
        }
    </style>
@endpush

@php
    $primaryLinks = [
        ['title' => 'Святині', 'image' => 'category-sanctuary.png', 'route' => 'faith.shrines'],
        ['title' => 'Рідні Боги', 'image' => 'category-gods.png', 'route' => 'faith.gods'],
        ['title' => 'Обряди', 'image' => 'category-rites.png', 'route' => 'faith.rituals'],
        ['title' => 'Слави', 'image' => 'category-glory.png', 'route' => 'faith.prayers'],
    ];



    $holidays = [
        ['title' => 'Велес', 'name' => 'Велес (Мороз, Микола)', 'image' => 'holiday-veles.png', 'position' => 'top-left'],
        ['title' => 'Коляда', 'name' => 'Різдво Коляди*', 'image' => 'holiday-kolyada.png', 'position' => 'top-center', 'featured' => true],
        ['title' => 'Водосвяття', 'name' => 'Водосвяття. Богоявлення', 'image' => 'holiday-vodo.png', 'position' => 'top-right'],
        ['title' => 'Мокоша', 'name' => 'Мокоша осіння', 'image' => 'holiday-mokosh.png', 'position' => 'left-one'],
        ['title' => 'Радогощ', 'name' => 'Радогощ. Різдво Миробога', 'image' => 'holiday-radogosh.png', 'position' => 'left-two', 'featured' => true],
        ['title' => 'Спас', 'name' => '2-й Спас. Яблучний. Великий', 'image' => 'holiday-spas.png', 'position' => 'left-three'],
        ['title' => 'Колодій', 'name' => 'Народження Колодки. Початок Колодія', 'image' => 'holiday-kolodiy.png', 'position' => 'right-one'],
        ['title' => 'Великдень', 'name' => 'Великдень*. Благовіщення', 'image' => 'holiday-velykden.png', 'position' => 'right-two', 'featured' => true],
        ['title' => 'Ярило Вишній', 'name' => 'Ярило Вишній', 'image' => 'holiday-yarylo.png', 'position' => 'right-three'],
        ['title' => 'Перун', 'name' => 'Перун', 'image' => 'holiday-perun.png', 'position' => 'bottom-left'],
        ['title' => 'Купайло', 'name' => 'Купало', 'image' => 'holiday-kupalo.png', 'position' => 'bottom-center', 'featured' => true],
        ['title' => 'Зелені святки', 'name' => 'Трійця', 'image' => 'holiday-zeleni.png', 'position' => 'bottom-right'],
    ];

@endphp

@section('content')
<section class="hero-home">
    <div class="hero-home__overlay"></div>
    <div class="hero-home__content">
        <p>Духовний центр</p>
        <h1>Рідна Віра</h1>
        <img src="{{ asset('assets/figma/home/ornament.png') }}" alt="">
    </div>
</section>

<section class="primary-links">
    <div class="figma-container">
        <div class="primary-links__grid">
            @foreach ($primaryLinks as $item)
                <a class="primary-card" href="{{ route($item['route']) }}">
                    <img src="{{ asset('assets/figma/home/'.$item['image']) }}" alt="{{ $item['title'] }}">
                    <strong>{{ $item['title'] }}</strong>
                </a>
            @endforeach
        </div>
    </div>
</section>

<section class="communities-section">
    <!-- cms-live:community-banner -->
    @include('communities.banner')
    <!-- /cms-live:community-banner -->
    <div class="figma-container communities-section__inner">
        <article class="paper-panel communities-panel">
            <h2>Громади та представництва</h2>

            <div class="communities-copy">
                <p>Духовний центр «Рідна Віра» об’єднує громади, духовних провідників та однодумців, які зберігають прадавній світогляд, народний звичай і живу обрядову традицію українців.</p>
                <p>На порталі зібрано відомості про громади та представництва, їхні події, свята, служіння і контакти для всіх, хто прагне долучитися до Рідної Віри.</p>
            </div>

            <!-- cms-live:featured-communities -->
            @include('communities.featured')
            <!-- /cms-live:featured-communities -->

            <a class="figma-button" href="{{ route('contact') }}#communities-map-title">Всі громади</a>
        </article>
    </div>
</section>

<section class="calendar-section" id="calendar">
    <div class="figma-container">
        <h2>Коло Свароже</h2>

        <div class="calendar-stage">
            <img class="calendar-wheel" src="{{ asset('assets/figma/home/wheel.png') }}" alt="Коло Свароже">

            @foreach ($holidays as $holiday)
                <a class="holiday-card holiday-card--{{ $holiday['position'] }} {{ !empty($holiday['featured']) ? 'holiday-card--featured' : '' }}"
                   href="{{ route('faith.holiday', Str::slug($holiday['name'])) }}"
                   aria-label="{{ $holiday['name'] }}">
                    <img src="{{ asset('assets/figma/home/'.$holiday['image']) }}" alt="">
                    <strong>{{ $holiday['title'] }}</strong>
                </a>
            @endforeach
        </div>

        <a class="figma-button" href="{{ route('faith.calendar') }}">Всі Свята</a>
    </div>
</section>

<!-- cms-live:materials -->
@foreach ($materialSections as $section)
<section class="materials-section" aria-labelledby="materials-title-{{ $loop->index }}">
    <div class="figma-container">
        <div class="publication-heading">
            <h2 id="materials-title-{{ $loop->index }}">{{ $section['title'] }}</h2>
            <a href="{{ $section['url'] }}">{{ $section['title'] === 'Новини' ? 'Усі новини' : ($section['title'] === 'Статті' ? 'Усі статті' : 'Уся творчість') }} →</a>
        </div>

        @if (!empty($section['items']))
        @php($featured = $section['items'][0])
        <div class="materials-grid">
            <a class="feature-material" href="{{ $featured['url'] }}">
                @include('partials.publication-image', ['visual' => $featured['image'] ?? null, 'placeholder' => $section['title']])
                <span class="feature-material__overlay"></span>
                @if (!empty($featured['date']))<time class="date-badge" datetime="{{ $featured['date'] }}">{{ date('d.m.Y', strtotime($featured['date'])) }}</time>@endif
                <span class="feature-material__content">
                    <strong>{{ $featured['title'] }}</strong>
                    <small>{{ $featured['excerpt'] }}</small>
                    <span class="feature-material__arrow">→</span>
                </span>
            </a>

            <div class="materials-list">
                @foreach (array_slice($section['items'], 1) as $item)
                    <a class="material-row" href="{{ $item['url'] }}">
                        @include('partials.publication-image', ['visual' => $item['image'] ?? null, 'sizes' => '(min-width: 600px) 154px, 100px', 'placeholder' => $section['title']])
                        <span>
                            <strong>{{ $item['title'] }}</strong>
                            @if (!empty($item['date']))<time class="date-badge" datetime="{{ $item['date'] }}">{{ date('d.m.Y', strtotime($item['date'])) }}</time>@elseif (!empty($item['author']))<small class="material-meta">{{ $item['author'] }}</small>@endif
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</section>
@endforeach
<!-- /cms-live:materials -->
@endsection
