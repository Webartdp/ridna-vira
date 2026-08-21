@extends('layouts.app')

@section('title', 'Календар свят — Рідна Віра')
@section('description', 'Календар свят Рідної Віри: Коло Свароже, основні дати року та рідновірські свята.')

@php
    $calendar = config('faith_calendar');
    $months = $calendar['months'] ?? [];
    $monthSlugs = [
        'Січень' => 'sichen',
        'Лютий' => 'liutyi',
        'Березень' => 'berezen',
        'Квітень' => 'kviten',
        'Травень' => 'traven',
        'Червень' => 'cherven',
        'Липень' => 'lypen',
        'Серпень' => 'serpen',
        'Вересень' => 'veresen',
        'Жовтень' => 'zhovten',
        'Листопад' => 'lystopad',
        'Грудень' => 'hruden',
    ];

    $mainHolidays = [
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
<section class="inner-hero inner-hero--faith" aria-labelledby="calendar-page-title">
    <div class="inner-hero__overlay"></div>
    <div class="inner-hero__content">
        <h1 id="calendar-page-title">Календар свят</h1>
        <nav class="inner-breadcrumbs" aria-label="Навігаційний шлях">
            <a href="{{ route('home') }}">Головна</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('faith') }}">Рідна Віра</a>
            <span aria-hidden="true">/</span>
            <span>Календар свят</span>
        </nav>
    </div>
</section>

<section class="faith-calendar-page">
    <div class="figma-container">
        <header class="faith-calendar-intro">
            <span class="faith-calendar-intro__eyebrow">Коло Свароже</span>
            <h2>Основні свята</h2>
            <p>Головні свята річного кола винесені окремо. Натисніть на свято, щоб перейти на його сторінку з описом.</p>
        </header>

        <div class="calendar-stage faith-calendar-wheel" aria-label="Основні свята Кола Сварожого">
            <img class="calendar-wheel" src="{{ asset('assets/figma/home/wheel.png') }}" alt="Коло Свароже">

            @foreach ($mainHolidays as $holiday)
                <a class="holiday-card holiday-card--{{ $holiday['position'] }} {{ !empty($holiday['featured']) ? 'holiday-card--featured' : '' }}"
                   href="{{ route('faith.holiday', Str::slug($holiday['name'])) }}">
                    <img src="{{ asset('assets/figma/home/'.$holiday['image']) }}" alt="">
                    <strong>{{ $holiday['title'] }}</strong>
                </a>
            @endforeach
        </div>

        <header class="faith-calendar-intro faith-calendar-intro--all">
            <span class="faith-calendar-intro__eyebrow">Повний календар</span>
            <h2>Усі свята</h2>
            <p>Нижче подано повний календар за місяцями. Основні свята також залишаються у цьому списку.</p>
        </header>

        <nav class="faith-calendar-index" aria-label="Місяці року">
            @foreach ($months as $month => $holidays)
                <a href="#{{ $monthSlugs[$month] ?? Str::slug($month) }}">{{ $month }}</a>
            @endforeach
        </nav>

        <div class="faith-calendar-grid">
            @foreach ($months as $month => $holidays)
                <section class="faith-month" id="{{ $monthSlugs[$month] ?? Str::slug($month) }}" aria-labelledby="month-{{ $monthSlugs[$month] ?? Str::slug($month) }}">
                    <header class="faith-month__header">
                        <span>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <h3 id="month-{{ $monthSlugs[$month] ?? Str::slug($month) }}">{{ $month }}</h3>
                    </header>

                    <div class="faith-month__list">
                        @foreach ($holidays as $holiday)
                            @php($holidaySlug = $holiday['slug'] ?? Str::slug($holiday['name']))
                            <article class="faith-holiday">
                                <a href="{{ route('faith.holiday', $holidaySlug) }}" aria-label="{{ $holiday['name'] }}"></a>
                                <time datetime="{{ $holiday['day'] }}">{{ $holiday['day'] }}</time>
                                <div>
                                    <h4>{{ $holiday['name'] }}</h4>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        @if (!empty($calendar['notes']))
            <aside class="faith-calendar-notes" aria-label="Примітки до календаря">
                @foreach ($calendar['notes'] as $note)
                    <p>{{ $note }}</p>
                @endforeach
            </aside>
        @endif

        <div class="faith-calendar-footer">
            <a class="document-back-link" href="{{ route('faith') }}">← До розділу «Рідна Віра»</a>
        </div>
    </div>
</section>
@endsection