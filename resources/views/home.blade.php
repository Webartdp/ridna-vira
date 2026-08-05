@extends('layouts.app')

@section('title', 'Рідна Віра — Духовний центр')
@section('description', 'Духовний центр «Рідна Віра»: громади, Рідні Боги, обряди, свята, новини та духовна спадщина українців.')

@php
    $primaryLinks = [
        ['title' => 'Святині', 'icon' => 'sanctuary', 'url' => url('/ridna-vira#sviatyni')],
        ['title' => 'Рідні Боги', 'icon' => 'gods', 'url' => url('/ridna-vira#ridni-bohy')],
        ['title' => 'Обряди', 'icon' => 'rites', 'url' => url('/ridna-vira#obriady')],
        ['title' => 'Слави', 'icon' => 'glory', 'url' => url('/ridna-vira#slavy')],
    ];

    $calendarItems = [
        ['title' => 'Велес', 'icon' => 'veles'],
        ['title' => 'Мокоша', 'icon' => 'mokosh'],
        ['title' => 'Білобог', 'icon' => 'bilobog'],
        ['title' => 'Дажбог', 'icon' => 'dazhbog'],
        ['title' => 'Спас', 'icon' => 'glory'],
        ['title' => 'Перун', 'icon' => 'perun'],
        ['title' => 'Коляда', 'icon' => 'kolyada'],
        ['title' => 'Купайло', 'icon' => 'kupala'],
        ['title' => 'Ярило Вишній', 'icon' => 'dazhbog'],
        ['title' => 'Водосвяття', 'icon' => 'rites'],
        ['title' => 'Зелені свята', 'icon' => 'feasts'],
        ['title' => 'Всі свята', 'icon' => 'feasts'],
    ];

    $contentSections = [
        [
            'title' => 'Новини',
            'url' => url('/novyny'),
            'featureTitle' => 'Світолад української оселі',
            'featureText' => 'Духовна спадщина, звичаї та символіка українського дому.',
            'items' => [
                'Структура замовляння у слов’ян',
                'Календар свят Рідної Віри',
                'Життя громад та духовних осередків',
            ],
        ],
        [
            'title' => 'Статті',
            'url' => url('/statti'),
            'featureTitle' => 'Світогляд Рідної Віри',
            'featureText' => 'Єдність людини, Роду, Природи та живої духовної традиції.',
            'items' => [
                'Рідні Боги у світогляді українців',
                'Сутність обряду та святодійства',
                'Коло Свароже і річний календар',
            ],
        ],
        [
            'title' => 'Творчість',
            'url' => url('/tvorchist'),
            'featureTitle' => 'Пісні, поезія та образи Роду',
            'featureText' => 'Творчі роботи, що продовжують духовну пам’ять і живу традицію.',
            'items' => [
                'Пісні та музичні твори',
                'Поезія Рідної Віри',
                'Образотворче мистецтво',
            ],
        ],
    ];
@endphp

@section('content')
<section class="hero" style="--hero-image: url('{{ asset('assets/hero.svg') }}')">
    <div class="hero__shade"></div>
    <div class="container site-container hero__content">
        <p class="hero__eyebrow">Духовний центр</p>
        <h1>Рідна Віра</h1>
        <img class="hero__ornament" src="{{ asset('assets/ornament.svg') }}" alt="">
    </div>
</section>

<section class="primary-links" aria-label="Основні розділи">
    <div class="container site-container">
        <div class="row g-3 g-lg-4">
            @foreach ($primaryLinks as $item)
                <div class="col-6 col-lg-3">
                    <a class="primary-card" href="{{ $item['url'] }}">
                        <span class="primary-card__icon">
                            <svg aria-hidden="true"><use href="{{ asset('assets/icons.svg') }}#{{ $item['icon'] }}"/></svg>
                        </span>
                        <strong>{{ $item['title'] }}</strong>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="communities" style="--communities-image: url('{{ asset('assets/communities.svg') }}')">
    <div class="communities__image" aria-hidden="true"></div>
    <div class="container site-container communities__inner">
        <article class="paper-card communities-card">
            <div class="section-heading">
                <span>Духовні осередки</span>
                <h2>Громади та представництва</h2>
            </div>

            <div class="row g-4 communities-copy">
                <div class="col-md-6">
                    <p>Духовний центр об’єднує громади Рідної Віри, духовних провідників та однодумців, які зберігають звичаї, обряди й світогляд наших Предків.</p>
                </div>
                <div class="col-md-6">
                    <p>На порталі представлено осередки, контакти громад, календар подій та матеріали для тих, хто прагне долучитися до живої української духовної традиції.</p>
                </div>
            </div>

            <div class="row g-3 g-lg-4 mt-1">
                @foreach (['Полум’я Роду', 'Права', 'Росичі'] as $index => $name)
                    <div class="col-md-4">
                        <a class="community-tile community-tile--{{ $index + 1 }}" href="#">
                            <span>{{ $name }}</span>
                        </a>
                    </div>
                @endforeach
            </div>

            <div class="text-center mt-4">
                <a class="btn btn-gold" href="#">Всі громади</a>
            </div>
        </article>
    </div>
</section>

<section class="svarog" id="calendar">
    <div class="container site-container">
        <div class="section-heading text-center">
            <span>Річне коло свят</span>
            <h2>Коло Свароже</h2>
        </div>

        <div class="svarog-map">
            <div class="svarog-map__top">
                @foreach (array_slice($calendarItems, 0, 3) as $item)
                    <a class="deity-card" href="#">
                        <svg aria-hidden="true"><use href="{{ asset('assets/icons.svg') }}#{{ $item['icon'] }}"/></svg>
                        <strong>{{ $item['title'] }}</strong>
                    </a>
                @endforeach
            </div>

            <div class="svarog-map__left">
                @foreach (array_slice($calendarItems, 3, 3) as $item)
                    <a class="deity-card" href="#">
                        <svg aria-hidden="true"><use href="{{ asset('assets/icons.svg') }}#{{ $item['icon'] }}"/></svg>
                        <strong>{{ $item['title'] }}</strong>
                    </a>
                @endforeach
            </div>

            <div class="svarog-map__wheel-wrap">
                <span class="svarog-map__glow" aria-hidden="true"></span>
                <img class="svarog-map__wheel" src="{{ asset('assets/wheel.svg') }}" alt="Коло Свароже — річний календар свят" loading="lazy">
            </div>

            <div class="svarog-map__right">
                @foreach (array_slice($calendarItems, 6, 3) as $item)
                    <a class="deity-card" href="#">
                        <svg aria-hidden="true"><use href="{{ asset('assets/icons.svg') }}#{{ $item['icon'] }}"/></svg>
                        <strong>{{ $item['title'] }}</strong>
                    </a>
                @endforeach
            </div>

            <div class="svarog-map__bottom">
                @foreach (array_slice($calendarItems, 9, 3) as $item)
                    <a class="deity-card" href="#">
                        <svg aria-hidden="true"><use href="{{ asset('assets/icons.svg') }}#{{ $item['icon'] }}"/></svg>
                        <strong>{{ $item['title'] }}</strong>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="text-center mt-4">
            <a class="btn btn-gold" href="{{ url('/ridna-vira') }}#calendar">Всі свята</a>
        </div>
    </div>
</section>

@foreach ($contentSections as $index => $section)
<section class="content-section {{ $index % 2 ? 'content-section--alt' : '' }}">
    <div class="container site-container">
        <div class="section-heading text-center">
            <span>Останні матеріали</span>
            <h2>{{ $section['title'] }}</h2>
        </div>

        <div class="row g-4 align-items-stretch">
            <div class="col-lg-7">
                <a class="featured-story" href="{{ $section['url'] }}">
                    <img src="{{ asset('assets/article.svg') }}" alt="{{ $section['featureTitle'] }}" loading="lazy">
                    <span class="featured-story__shade"></span>
                    <span class="featured-story__content">
                        <small class="date-pill">20.03.2027</small>
                        <strong>{{ $section['featureTitle'] }}</strong>
                        <span>{{ $section['featureText'] }}</span>
                        <b aria-hidden="true">→</b>
                    </span>
                </a>
            </div>

            <div class="col-lg-5">
                <div class="story-list">
                    @foreach ($section['items'] as $item)
                        <a class="story-item" href="{{ $section['url'] }}">
                            <img src="{{ asset('assets/article.svg') }}" alt="" loading="lazy">
                            <span class="story-item__body">
                                <strong>{{ $item }}</strong>
                                <small>20.03.2027</small>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="text-center mt-4 d-lg-none">
            <a class="btn btn-gold" href="{{ $section['url'] }}">Усі матеріали</a>
        </div>
    </div>
</section>
@endforeach
@endsection
