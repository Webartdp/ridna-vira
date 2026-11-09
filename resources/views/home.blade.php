@extends('layouts.app')

@section('title', 'Рідна Віра — Духовний центр')
@section('body-class', 'home-page')
@section('description', 'Жива українська духовна традиція. Пізнавайте Рідну Віру, знаходьте свою громаду, долучайтеся до свят, обрядів та навчання.')

@push('styles')
    <link rel="preload" href="{{ asset('fonts/Holos-Bold.ttf') }}" as="font" type="font/ttf" crossorigin>
    <link rel="preload" href="{{ asset('fonts/KyivRegion-Regular.woff2') }}" as="font" type="font/woff2" crossorigin>
    @vite('resources/scss/home.scss')
@endpush

@php
    $directions = [
        ['title' => 'Віра', 'text' => 'Світогляд, Рідні Боги та духовні основи.', 'href' => route('faith')],
        ['title' => 'Громади', 'text' => 'Знайомство, спільні свята та живе спілкування.', 'href' => route('contact').'#communities-map-title'],
        ['title' => 'Академія', 'text' => 'Знання, що допомагають глибше розуміти традицію.', 'href' => 'https://ridnovir.in.ua/'],
        ['title' => 'Обряди', 'text' => 'Святодійства та звичаї в колі громади.', 'href' => route('faith.rituals')],
        ['title' => 'Книги й матеріали', 'text' => 'Тексти для дослідження та власних відкриттів.', 'href' => route('faith.books')],
        ['title' => 'Крамниця', 'text' => 'Простір речей, пов’язаних із традицією.', 'href' => url('/kramnychka')],
    ];
@endphp

@section('content')
<div class="rv-home">
    <section class="rv-hero" aria-labelledby="home-title">
        <img class="rv-hero__image" src="{{ asset('assets/home/hero-1280.webp') }}"
             srcset="{{ asset('assets/home/hero-640.webp') }} 640w, {{ asset('assets/home/hero-1280.webp') }} 1280w"
             sizes="100vw" width="1280" height="848" fetchpriority="high" alt="Святиня Рідної Віри серед зеленого лісу">
        <div class="rv-wrap rv-hero__content">
            <p class="rv-eyebrow">Духовний центр · Україна</p>
            <h1 id="home-title">Рідна Віра</h1>
            <p class="rv-hero__thesis">Своє коріння.<br>Жива традиція.</p>
            <p class="rv-hero__intro">Єднаємо людей у шануванні Рідних Богів, пам’яті Предків та любові до рідної землі.</p>
            <div class="rv-actions">
                <a class="rv-button" href="{{ route('about') }}">Дізнатися більше <span aria-hidden="true">↗</span></a>
                <a class="rv-button rv-button--outline" href="{{ route('contact') }}#communities-map-title">Знайти громаду <span aria-hidden="true">→</span></a>
            </div>
        </div>
        <div class="rv-wrap rv-hero__foot"><span>Традиція, яку проживаємо сьогодні</span><a href="#who-we-are">Пізнати ближче <span aria-hidden="true">↓</span></a></div>
    </section>

    <section class="rv-section rv-about" id="who-we-are" aria-labelledby="who-title">
        <div class="rv-wrap rv-split">
            <div><p class="rv-eyebrow">01 / Хто ми</p><h2 id="who-title">Укорінені в минулому.<br>Відкриті до майбутнього.</h2></div>
            <div class="rv-about__copy">
                <p class="rv-lead">«Рідна Віра» — духовний центр, що єднає громади та людей, для яких українська традиція є частиною щоденного життя.</p>
                <p>Ми шануємо Рідних Богів і Предків, пізнаємо народний світогляд та вчимося жити в злагоді з Природою. Через спільні свята, обряди й освіту передаємо цей досвід далі.</p>
                <a class="rv-link" href="{{ route('about') }}">Про центр і наші цінності <span aria-hidden="true">↗</span></a>
            </div>
        </div>
        <div class="rv-wrap rv-values" aria-label="Наші цінності"><span>Пам’ять Роду</span><span>Єдність із Природою</span><span>Жива громада</span></div>
    </section>

    <section class="rv-section rv-calendar" aria-labelledby="home-calendar-title">
        <div class="rv-wrap">
            <div class="rv-section-head"><div><p class="rv-eyebrow">02 / Ритм року</p><h2 id="home-calendar-title">Коло Свароже</h2></div><a class="rv-link" href="{{ route('faith.calendar') }}">Увесь календар <span aria-hidden="true">↗</span></a></div>
            <p class="rv-section-intro">У кожної пори — свій зміст. Найближчі свята нашого річного кола.</p>
            <div class="rv-holidays">
                @foreach ($upcomingHolidays as $holiday)
                    <a class="rv-holiday" href="{{ route('faith.holiday', $holiday['slug']) }}">
                        <time datetime="{{ $holiday['date']->format('Y-m-d') }}">{{ $holiday['date']->format('d.m') }} <span>{{ $holiday['date']->format('Y') }}</span></time>
                        <img src="{{ asset('assets/home/'.$holiday['image'].'-192.webp') }}" width="192" height="192" loading="lazy" decoding="async" alt="">
                        <h3>{{ $holiday['name'] }}</h3><span class="rv-holiday__more">Про свято <span aria-hidden="true">↗</span></span>
                    </a>
                @endforeach
            </div>
            <p class="rv-note">Дати — за календарем центру. Час і місце спільного святкування уточнюйте у своїй громаді.</p>
        </div>
    </section>

    <section class="rv-section rv-directions" aria-labelledby="directions-title">
        <div class="rv-wrap">
            <div class="rv-section-head"><div><p class="rv-eyebrow">03 / Напрями</p><h2 id="directions-title">Знайдіть свій шлях</h2></div><p>Пізнавати. Долучатися.<br>Творити разом.</p></div>
            <div class="rv-directions__grid">
                @foreach ($directions as $direction)
                    <a class="rv-direction" href="{{ $direction['href'] }}">
                        <span class="rv-direction__top"><span>{{ sprintf('%02d', $loop->iteration) }}</span><span aria-hidden="true">↗</span></span>
                        <h3>{{ $direction['title'] }}</h3><p>{{ $direction['text'] }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="rv-community" aria-labelledby="community-title">
        <div class="rv-community__photo"><img src="{{ asset('assets/home/community-960.webp') }}" srcset="{{ asset('assets/home/community-480.webp') }} 480w, {{ asset('assets/home/community-960.webp') }} 960w" sizes="(min-width: 900px) 50vw, 100vw" width="960" height="720" alt="Учасники громади Рідної Віри на спільній зустрічі" loading="lazy" decoding="async"></div>
        <div class="rv-community__copy"><p class="rv-eyebrow">04 / Поруч із вами</p><h2 id="community-title">Рідна Віра<br>по Україні</h2><p class="rv-lead">Традиція живе там,<br>де збираються люди.</p><p>Знайдіть громаду чи представництво у своєму краї. Познайомтеся з духовними провідниками та дізнайтеся, як долучитися до зустрічей і свят.</p><a class="rv-button" href="{{ route('contact') }}#communities-map-title">Переглянути мапу громад <span aria-hidden="true">↗</span></a><a class="rv-link" href="{{ route('contact') }}">Немає осередку поруч? Зв’яжіться з нами <span aria-hidden="true">→</span></a></div>
    </section>

    <section class="rv-section rv-journal" aria-labelledby="journal-title">
        <div class="rv-wrap">
            <div class="rv-section-head"><div><p class="rv-eyebrow">05 / Читати й пізнавати</p><h2 id="journal-title">Слово і думка</h2></div><a class="rv-link" href="{{ route('articles.index') }}">Усі статті <span aria-hidden="true">↗</span></a></div>
            <div class="rv-journal__grid">
                <a class="rv-editorial" href="{{ route('articles.index') }}"><img src="{{ asset('assets/home/journal-650.webp') }}" width="650" height="436" alt="Українська оселя під солом’яною стріхою серед квітів" loading="lazy" decoding="async"><div class="rv-editorial__copy"><span class="rv-eyebrow">Бібліотека традиції</span><h3>Пізнавати своє —<br>відкривати більше</h3><span>Світогляд, звичаї та духовна спадщина <span aria-hidden="true">↗</span></span></div></a>
                <div class="rv-reading-list">
                    @forelse ($homeArticles as $article)
                        <a class="rv-reading" href="{{ route('articles.show', $article['slug']) }}"><span class="rv-eyebrow">З бібліотеки · {{ sprintf('%02d', $loop->iteration) }}</span><h3>{{ $article['title'] }}</h3><span class="rv-link">Читати статтю <span aria-hidden="true">↗</span></span></a>
                    @empty
                        <a class="rv-reading" href="{{ route('faith.books') }}"><span class="rv-eyebrow">Для самостійного пізнання</span><h3>Книги про Рідну Віру</h3><p>Відкрийте бібліотеку духовної спадщини.</p><span class="rv-link">Переглянути книги <span aria-hidden="true">↗</span></span></a>
                        <a class="rv-reading" href="{{ route('faith') }}"><span class="rv-eyebrow">Перші кроки</span><h3>Основи нашого світогляду</h3><span class="rv-link">Пізнати Рідну Віру <span aria-hidden="true">↗</span></span></a>
                    @endforelse
                    <a class="rv-news-link" href="{{ url('/novyny') }}">Новини центру <span aria-hidden="true">↗</span></a>
                </div>
            </div>
        </div>
    </section>

    <section class="rv-section rv-academy" aria-labelledby="academy-title">
        <div class="rv-wrap rv-academy__grid">
            <div class="rv-academy__symbol" aria-hidden="true"><span></span></div>
            <div><p class="rv-eyebrow">06 / Академія Рідної Віри</p><h2 id="academy-title">Знання, що<br>стають опорою</h2><p class="rv-lead">Поглиблюйте розуміння української духовної традиції.</p><p>Досліджуйте світогляд, календар і обрядовість. Знайомтеся з навчальними матеріалами Академії та звертайтеся до центру щодо можливостей навчання.</p><div class="rv-actions"><a class="rv-button" href="https://ridnovir.in.ua/">Перейти до Академії <span aria-hidden="true">↗</span></a><a class="rv-link" href="{{ route('contact') }}">Запитати про навчання <span aria-hidden="true">→</span></a></div></div>
        </div>
    </section>

    <section class="rv-section rv-invitation" aria-labelledby="invitation-title"><div class="rv-wrap"><p class="rv-eyebrow">Ваше місце в колі</p><h2 id="invitation-title">Почнімо зі знайомства</h2><p>Приходьте з питаннями, цікавістю та відкритістю.<br>Знайдімо шлях до традиції разом.</p><div class="rv-actions"><a class="rv-button" href="{{ route('contact') }}#communities-map-title">Знайти свою громаду <span aria-hidden="true">↗</span></a><a class="rv-button rv-button--outline" href="{{ route('contact') }}">Зв’язатися з центром <span aria-hidden="true">→</span></a></div></div></section>
</div>
@endsection
