@extends('layouts.app')

@section('title', 'Рідна Віра — Духовний центр')
@section('description', 'Офіційний сайт Духовного центру «Рідна Віра»: громади, свята, обряди, новини, статті та творчість.')

@php
    $primaryLinks = [
        ['title' => 'Святині', 'image' => 'category-sanctuary.png'],
        ['title' => 'Рідні Боги', 'image' => 'category-gods.png'],
        ['title' => 'Обряди', 'image' => 'category-rites.png'],
        ['title' => 'Слави', 'image' => 'category-glory.png'],
    ];

    $communities = ['Полум’я Роду', 'Права', 'Росичі'];

    $holidays = [
        ['name' => 'Велес', 'image' => 'holiday-veles.png', 'position' => 'top-left'],
        ['name' => 'Коляда', 'image' => 'holiday-kolyada.png', 'position' => 'top-center', 'featured' => true],
        ['name' => 'ВОДОсвяття', 'image' => 'holiday-vodo.png', 'position' => 'top-right'],
        ['name' => 'Мокоша', 'image' => 'holiday-mokosh.png', 'position' => 'left-one'],
        ['name' => 'Радогощ', 'image' => 'holiday-radogosh.png', 'position' => 'left-two', 'featured' => true],
        ['name' => 'Спас', 'image' => 'holiday-spas.png', 'position' => 'left-three'],
        ['name' => 'Колодій', 'image' => 'holiday-kolodiy.png', 'position' => 'right-one'],
        ['name' => 'Великдень', 'image' => 'holiday-velykden.png', 'position' => 'right-two', 'featured' => true],
        ['name' => 'Ярило Вишній', 'image' => 'holiday-yarylo.png', 'position' => 'right-three'],
        ['name' => 'Перун', 'image' => 'holiday-perun.png', 'position' => 'bottom-left'],
        ['name' => 'Купайло', 'image' => 'holiday-kupalo.png', 'position' => 'bottom-center', 'featured' => true],
        ['name' => 'Зелені святки', 'image' => 'holiday-zeleni.png', 'position' => 'bottom-right'],
    ];

    $sections = [
        ['title' => 'Новини', 'route' => '/novyny'],
        ['title' => 'Статті', 'route' => '/statti'],
        ['title' => 'Творчість', 'route' => '/tvorchist'],
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
                <a class="primary-card" href="#">
                    <img src="{{ asset('assets/figma/home/'.$item['image']) }}" alt="{{ $item['title'] }}">
                    <strong>{{ $item['title'] }}</strong>
                </a>
            @endforeach
        </div>
    </div>
</section>

<section class="communities-section">
    <div class="communities-section__background"></div>
    <div class="figma-container communities-section__inner">
        <article class="paper-panel communities-panel">
            <h2>Громади та представництва</h2>

            <div class="communities-copy">
                <p>Духовний центр «Рідна Віра» об’єднує громади, духовних провідників та однодумців, які зберігають прадавній світогляд, народний звичай і живу обрядову традицію українців.</p>
                <p>На порталі зібрано відомості про громади та представництва, їхні події, свята, служіння і контакти для всіх, хто прагне долучитися до Рідної Віри.</p>
            </div>

            <div class="communities-grid">
                @foreach ($communities as $community)
                    <a class="community-card" href="#">
                        <img src="{{ asset('assets/figma/home/community-card.png') }}" alt="">
                        <span>{{ $community }}</span>
                    </a>
                @endforeach
            </div>

            <a class="figma-button" href="#">Всі громади</a>
        </article>
    </div>
</section>

<section class="calendar-section" id="calendar">
    <div class="figma-container">
        <h2>Коло Свароже</h2>

        <div class="calendar-stage">
            <img class="calendar-wheel" src="{{ asset('assets/figma/home/wheel.png') }}" alt="Коло Свароже">

            @foreach ($holidays as $holiday)
                <a class="holiday-card holiday-card--{{ $holiday['position'] }} {{ !empty($holiday['featured']) ? 'holiday-card--featured' : '' }}" href="#">
                    <img src="{{ asset('assets/figma/home/'.$holiday['image']) }}" alt="">
                    <strong>{{ $holiday['name'] }}</strong>
                </a>
            @endforeach
        </div>

        <a class="figma-button" href="#">Всі Свята</a>
    </div>
</section>

@foreach ($sections as $section)
<section class="materials-section">
    <div class="figma-container">
        <h2>{{ $section['title'] }}</h2>

        <div class="materials-grid">
            <a class="feature-material" href="{{ url($section['route']) }}">
                <img src="{{ asset('assets/figma/home/article-feature.png') }}" alt="Світолад української оселі">
                <span class="feature-material__overlay"></span>
                <span class="date-badge">20.03.2027</span>
                <span class="feature-material__content">
                    <strong>Світолад української оселі</strong>
                    <small>Духовна спадщина, звичаї та символіка українського дому.</small>
                    <span class="feature-material__arrow">→</span>
                </span>
            </a>

            <div class="materials-list">
                @for ($i = 0; $i < 3; $i++)
                    <a class="material-row" href="{{ url($section['route']) }}">
                        <img src="{{ asset('assets/figma/home/article-thumb.png') }}" alt="">
                        <span>
                            <strong>Структура замовляння у слов’ян</strong>
                            <small class="date-badge">20.03.2027</small>
                        </span>
                    </a>
                @endfor
            </div>
        </div>
    </div>
</section>
@endforeach
@endsection
