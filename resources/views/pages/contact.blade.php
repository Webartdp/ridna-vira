@extends('layouts.app')

@section('title', 'Зв’язок — Рідна Віра')
@section('description', 'Карта громад, представництв і контакти проводу Духовного центру Рідна Віра.')

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@php
    $communityLocations = [
        [
            'id' => 'zaporizhzhia',
            'city' => 'Запоріжжя',
            'region' => 'Запорізька область',
            'circle' => 'zaporizke',
            'circleLabel' => 'Запорозьке Коло',
            'lat' => 47.8388,
            'lng' => 35.1396,
            'entries' => [
                [
                    'type' => 'community',
                    'circle' => 'zaporizke',
                    'title' => 'Громада «Права»',
                    'role' => 'Голова, жрець',
                    'person' => 'Микола Кардач',
                    'phones' => ['093 994 61 52'],
                    'emails' => ['panmykolazp@gmail.com'],
                ],
                [
                    'type' => 'community',
                    'circle' => 'zaporizke',
                    'title' => 'Громада «Сварга»',
                    'role' => 'Голова, обрядодій',
                    'person' => 'Ярослав Свидрань',
                    'phones' => ['096 091 16 37', '063 671 36 25'],
                    'emails' => [],
                ],
                [
                    'type' => 'community',
                    'circle' => 'zaporizke',
                    'title' => 'Громада «Арійський шлях»',
                    'role' => 'Голова, берегиня',
                    'person' => 'Ясна Яковенко',
                    'phones' => ['063 153 26 81', '099 970 92 12'],
                    'emails' => ['yana.yakovenko@gmail.com'],
                ],
                [
                    'type' => 'community',
                    'circle' => 'zaporizke',
                    'title' => 'Громада «Внуки Велеса»',
                    'role' => 'Голова, жриця',
                    'person' => 'Ярина Яніна',
                    'phones' => ['066 204 44 30'],
                    'emails' => [],
                ],
            ],
        ],
        [
            'id' => 'mykolaiv',
            'city' => 'Миколаїв',
            'region' => 'Миколаївська область',
            'circle' => 'zaporizke',
            'circleLabel' => 'Запорозьке Коло',
            'lat' => 46.9750,
            'lng' => 31.9946,
            'entries' => [[
                'type' => 'community',
                'circle' => 'zaporizke',
                'title' => 'Громада «Колограй»',
                'role' => 'Лютий (Сергій) Щербаков та обрядодія Перуниця (Антоніна) Квасниця',
                'person' => '',
                'phones' => ['063 286 86 41', '066 222 54 73'],
                'emails' => [],
            ]],
        ],
        [
            'id' => 'volodarka',
            'city' => 'Володарка',
            'region' => 'Київська область',
            'circle' => 'zaporizke',
            'circleLabel' => 'Запорозьке Коло',
            'lat' => 49.5249,
            'lng' => 29.9122,
            'entries' => [[
                'type' => 'community',
                'circle' => 'zaporizke',
                'title' => 'Громада «Росичі»',
                'role' => 'Голова',
                'person' => 'Леонід Гапич (Северин)',
                'address' => 'вул. Миру, 7',
                'phones' => ['068 554 04 08', '050 781 50 44'],
                'emails' => ['oleon888@ukr.net'],
            ]],
        ],
        [
            'id' => 'kyiv',
            'city' => 'Київ',
            'region' => 'місто Київ',
            'circle' => 'zaporizke',
            'circleLabel' => 'Запорозьке Коло',
            'lat' => 50.4501,
            'lng' => 30.5234,
            'entries' => [[
                'type' => 'representation',
                'circle' => 'zaporizke',
                'title' => 'Представництво Рідної Віри',
                'role' => 'Представник, жрець',
                'person' => 'Ярун Воєводін',
                'phones' => ['098 336 19 24', '093 800 12 45', '099 350 82 29'],
                'emails' => [],
            ]],
        ],
        [
            'id' => 'dnipro',
            'city' => 'Дніпро',
            'region' => 'Січеславська область',
            'circle' => 'sicheslavske',
            'circleLabel' => 'Січеславське Коло',
            'lat' => 48.4647,
            'lng' => 35.0462,
            'entries' => [[
                'type' => 'community',
                'circle' => 'sicheslavske',
                'title' => 'Громада «Полум’я Роду»',
                'role' => 'Голова, волхв',
                'person' => 'Яромир Мирошніченко',
                'phones' => ['093 414 20 16'],
                'emails' => ['rid@svarga.dp.ua', 'jaromir@yaro.dp.ua'],
            ]],
        ],
        [
            'id' => 'pavlohrad',
            'city' => 'Павлоград',
            'region' => 'Січеславська область',
            'circle' => 'sicheslavske',
            'circleLabel' => 'Січеславське Коло',
            'lat' => 48.5343,
            'lng' => 35.8705,
            'entries' => [[
                'type' => 'representation',
                'circle' => 'sicheslavske',
                'title' => 'Представництво Рідної Віри',
                'role' => 'Представник',
                'person' => 'Серга Бондар',
                'phones' => ['095 583 41 10'],
                'emails' => ['hors8@ukr.net'],
            ]],
        ],
        [
            'id' => 'nikopol',
            'city' => 'Нікополь',
            'region' => 'Січеславська область',
            'circle' => 'sicheslavske',
            'circleLabel' => 'Січеславське Коло',
            'lat' => 47.5712,
            'lng' => 34.3964,
            'entries' => [[
                'type' => 'community',
                'circle' => 'sicheslavske',
                'title' => 'Громада «Матир-Сва»',
                'role' => 'Староста',
                'person' => 'Світлозара Ісаєва',
                'phones' => ['093 342 30 13', '095 023 60 84'],
                'emails' => [],
            ]],
        ],
        [
            'id' => 'bezliudivka',
            'city' => 'Безлюдівка',
            'region' => 'Харківська область',
            'circle' => 'sicheslavske',
            'circleLabel' => 'Січеславське Коло',
            'lat' => 49.8708,
            'lng' => 36.2584,
            'entries' => [[
                'type' => 'community',
                'circle' => 'sicheslavske',
                'title' => 'Громада «Святославичі»',
                'role' => 'Голова, волхв',
                'person' => 'Вірослав Зозуля',
                'phones' => ['095 474 46 68'],
                'emails' => ['pocondiy.viroslav@gmail.com'],
            ]],
        ],
        [
            'id' => 'sumy',
            'city' => 'Суми',
            'region' => 'Сумська область',
            'circle' => 'sicheslavske',
            'circleLabel' => 'Січеславське Коло',
            'lat' => 50.9077,
            'lng' => 34.7981,
            'entries' => [[
                'type' => 'representation',
                'circle' => 'sicheslavske',
                'title' => 'Представництво Рідної Віри',
                'role' => 'Представник',
                'person' => 'Богдан Яровий',
                'phones' => ['063 709 52 88'],
                'emails' => [],
            ]],
        ],
        [
            'id' => 'poltava',
            'city' => 'Полтава',
            'region' => 'Полтавська область',
            'circle' => 'sicheslavske',
            'circleLabel' => 'Січеславське Коло',
            'lat' => 49.5883,
            'lng' => 34.5514,
            'entries' => [[
                'type' => 'representation',
                'circle' => 'sicheslavske',
                'title' => 'Представництво Рідної Віри',
                'role' => 'Представник',
                'person' => 'Велеслав Біленький',
                'phones' => ['096 624 41 10'],
                'emails' => ['rostyslavrvbilenkyy@gmail.com'],
            ]],
        ],
        [
            'id' => 'ternopil',
            'city' => 'Тернопіль',
            'region' => 'Тернопільська область',
            'circle' => 'zahidne',
            'circleLabel' => 'Західне Коло',
            'lat' => 49.5535,
            'lng' => 25.5948,
            'entries' => [[
                'type' => 'community',
                'circle' => 'zahidne',
                'title' => 'Громада «Велесія»',
                'role' => 'Голова, жриця',
                'person' => 'Зореквіта Біленька',
                'phones' => ['098 387 03 95'],
                'emails' => [],
            ]],
        ],
        [
            'id' => 'khmelnytskyi',
            'city' => 'Хмельницький',
            'region' => 'Хмельницька область',
            'circle' => 'zahidne',
            'circleLabel' => 'Західне Коло',
            'lat' => 49.4229,
            'lng' => 26.9871,
            'entries' => [[
                'type' => 'community',
                'circle' => 'zahidne',
                'title' => 'Громада «Коло Творення»',
                'role' => 'Жриця Мілада Фастова та жрець Асур Яровий',
                'person' => '',
                'phones' => ['097 233 19 00', '068 050 75 94'],
                'emails' => [],
            ]],
        ],
        [
            'id' => 'maliivtsi',
            'city' => 'Маліївці',
            'region' => 'Хмельницька область',
            'circle' => 'zahidne',
            'circleLabel' => 'Західне Коло',
            'lat' => 48.9959,
            'lng' => 26.9904,
            'entries' => [[
                'type' => 'representation',
                'circle' => 'zahidne',
                'title' => 'Представництво громади «Коло Творення»',
                'role' => 'Представниця, обрядодія',
                'person' => 'Яра Желавська',
                'phones' => ['097 966 92 12'],
                'emails' => [],
            ]],
        ],
        [
            'id' => 'letychiv',
            'city' => 'Летичів',
            'region' => 'Хмельницька область',
            'circle' => 'zahidne',
            'circleLabel' => 'Західне Коло',
            'lat' => 49.3829,
            'lng' => 27.6305,
            'entries' => [[
                'type' => 'representation',
                'circle' => 'zahidne',
                'title' => 'Представництво громади «Коло Творення»',
                'role' => 'Представник',
                'person' => 'Ігор Сухорук',
                'phones' => ['095 524 84 56'],
                'emails' => [],
            ]],
        ],
        [
            'id' => 'shyroka-hreblia',
            'city' => 'Широка Гребля',
            'region' => 'Вінницька область',
            'circle' => 'zahidne',
            'circleLabel' => 'Західне Коло',
            'lat' => 49.1665,
            'lng' => 28.2377,
            'entries' => [[
                'type' => 'community',
                'circle' => 'zahidne',
                'title' => 'Громада «Велес»',
                'role' => 'Голова',
                'person' => 'Олекса Покотило',
                'phones' => ['097 112 29 27'],
                'emails' => [],
            ]],
        ],
        [
            'id' => 'vinnytsia',
            'city' => 'Вінниця',
            'region' => 'Вінницька область',
            'circle' => 'zahidne',
            'circleLabel' => 'Західне Коло',
            'lat' => 49.2331,
            'lng' => 28.4682,
            'entries' => [[
                'type' => 'representation',
                'circle' => 'zahidne',
                'title' => 'Представництво Рідної Віри',
                'role' => 'Представник',
                'person' => 'Богуслав Сулима',
                'phones' => ['067 587 57 10'],
                'emails' => [],
            ]],
        ],
        [
            'id' => 'dubrovytsia',
            'city' => 'Дубровиця',
            'region' => 'Рівненська область',
            'circle' => 'zahidne',
            'circleLabel' => 'Західне Коло',
            'lat' => 51.5710,
            'lng' => 26.5650,
            'entries' => [[
                'type' => 'representation',
                'circle' => 'zahidne',
                'title' => 'Представництво Рідної Віри',
                'role' => 'Представник',
                'person' => 'Орій Колода',
                'phones' => ['063 187 72 88'],
                'emails' => [],
            ]],
        ],
        [
            'id' => 'berdychiv',
            'city' => 'Бердичів',
            'region' => 'Житомирська область',
            'circle' => 'zahidne',
            'circleLabel' => 'Західне Коло',
            'lat' => 49.8918,
            'lng' => 28.6000,
            'entries' => [[
                'type' => 'representation',
                'circle' => 'zahidne',
                'title' => 'Представництво Рідної Віри',
                'role' => 'Представник',
                'person' => 'Велеслав Гвоздецький',
                'phones' => ['096 363 64 25'],
                'emails' => [],
            ]],
        ],
        [
            'id' => 'kolomyia',
            'city' => 'Коломия',
            'region' => 'Івано-Франківська область',
            'circle' => 'zahidne',
            'circleLabel' => 'Західне Коло',
            'lat' => 48.5305,
            'lng' => 25.0403,
            'entries' => [[
                'type' => 'representation',
                'circle' => 'zahidne',
                'title' => 'Представництво Рідної Віри',
                'role' => 'Представник',
                'person' => 'Хорт Мосюк',
                'phones' => ['097 340 48 68'],
                'emails' => [],
            ]],
        ],
    ];

    $communityEntryCount = collect($communityLocations)->sum(fn ($location) => count($location['entries']));
@endphp

@section('content')
<section class="inner-hero inner-hero--about" aria-labelledby="contact-page-title">
    <div class="inner-hero__overlay"></div>
    <div class="inner-hero__content">
        <h1 id="contact-page-title">Зв’язок</h1>
        <nav class="inner-breadcrumbs" aria-label="Навігаційний шлях">
            <a href="{{ route('home') }}">Головна</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('about') }}">Про центр</a>
            <span aria-hidden="true">/</span>
            <span>Зв’язок</span>
        </nav>
    </div>
</section>

<section class="center-page center-page--contact">
    <div class="figma-container">
        <div class="center-intro center-intro--narrow">
            <p>Оберіть найближчу громаду або представництво на карті. Натисніть на позначку, щоб побачити коротку інформацію, ім’я провідника та доступні контакти.</p>
        </div>

        <section class="center-section" aria-labelledby="contact-main-title">
            <div class="center-section__heading">
                <span>Центральний зв’язок</span>
                <h2 id="contact-main-title">Провід Духовного центру</h2>
            </div>

            <div class="contact-main-grid contact-main-grid--three">
                <article class="contact-card contact-card--primary">
                    <span>Голова ДЦ Рідної Віри, Волхв</span>
                    <h3>Світовит Пашник</h3>
                    <p>Запоріжжя</p>
                    <a href="tel:+380686439328">068 643 93 28</a>
                    <a href="tel:+380661537179">066 153 71 79</a>
                    <a href="mailto:pashnyk@ukr.net">pashnyk@ukr.net</a>
                    <a href="mailto:svitovyt@gmail.com">svitovyt@gmail.com</a>
                </article>

                <article class="contact-card contact-card--primary">
                    <span>Голова Управи, Волхв</span>
                    <h3>Яромир Мирошніченко</h3>
                    <p>Дніпро</p>
                    <a href="tel:+380934142016">093 414 20 16</a>
                    <a href="mailto:jaromirdp@gmail.com">jaromirdp@gmail.com</a>
                </article>

                <article class="contact-card contact-card--primary">
                    <span>Навчально-просвітницький відділ, Берегиня</span>
                    <h3>Ясна Яковенко</h3>
                    <p>Запоріжжя</p>
                    <a href="tel:+380631532681">063 153 26 81</a>
                    <a href="tel:+380999709212">099 970 92 12</a>
                    <a href="mailto:yana.yakovenko@gmail.com">yana.yakovenko@gmail.com</a>
                </article>
            </div>
        </section>

        <section class="community-map-section" aria-labelledby="communities-map-title" data-community-map>
            <div class="center-section__heading">
                <span>Громади та представництва</span>
                <h2 id="communities-map-title">Мапа Рідної Віри в Україні</h2>
            </div>
            <p class="community-map-intro">На мапі зібрано {{ $communityEntryCount }} громад і представництв. Можна шукати за містом, назвою громади або ім’ям представника та окремо переглядати крайові Кола.</p>

            <div class="community-map-toolbar">
                <label class="community-map-field">
                    <span>Пошук</span>
                    <input type="search" placeholder="Місто, громада або ім’я" data-community-map-search>
                </label>

                <label class="community-map-field">
                    <span>Крайове Коло</span>
                    <select data-community-map-circle>
                        <option value="all">Усі Кола</option>
                        <option value="zaporizke">Запорозьке Коло</option>
                        <option value="sicheslavske">Січеславське Коло</option>
                        <option value="zahidne">Західне Коло</option>
                    </select>
                </label>

                <div class="community-map-types" aria-label="Тип осередку">
                    <button class="community-map-filter is-active" type="button" data-community-map-type="all">Усі</button>
                    <button class="community-map-filter" type="button" data-community-map-type="community">Громади</button>
                    <button class="community-map-filter" type="button" data-community-map-type="representation">Представництва</button>
                </div>
            </div>

            <div class="community-map-shell">
                <div class="community-map-canvas" data-community-map-canvas aria-label="Інтерактивна мапа громад і представництв Рідної Віри"></div>

                <aside class="community-map-directory" aria-label="Перелік громад і представництв">
                    <div class="community-map-directory__head">
                        <span>Знайдено осередків</span>
                        <strong><b data-community-map-count>{{ $communityEntryCount }}</b> у переліку</strong>
                    </div>

                    <div class="community-map-locations">
                        @foreach ($communityLocations as $location)
                            <article class="community-location" data-map-location="{{ $location['id'] }}">
                                <button class="community-location__button" type="button" data-map-location-button>
                                    <span>
                                        <span class="community-location__city">{{ $location['city'] }}</span>
                                        <span class="community-location__meta">{{ $location['region'] }} · {{ $location['circleLabel'] }}</span>
                                    </span>
                                    <span class="community-location__count">{{ count($location['entries']) }}</span>
                                </button>

                                <div class="community-location__entries">
                                    @foreach ($location['entries'] as $entry)
                                        <div class="community-location__entry">
                                            <b>{{ $entry['title'] }}</b>
                                            <small>{{ collect([$entry['role'], $entry['person']])->filter()->implode(' — ') }}</small>
                                        </div>
                                    @endforeach
                                </div>
                            </article>
                        @endforeach

                        <p class="community-map-empty" data-community-map-empty hidden>За вибраними умовами нічого не знайдено.</p>
                    </div>
                </aside>
            </div>

            <div class="community-map-legend" aria-label="Позначення на мапі">
                <span><i></i> Громада</span>
                <span><i class="is-representation"></i> Представництво</span>
            </div>
        </section>

        <section class="contact-action-panel" aria-labelledby="contact-action-title">
            <div>
                <span>Немає осередку у вашому місті?</span>
                <h2 id="contact-action-title">Створити громаду або запросити духовного провідника</h2>
                <p>Зверніться до Управи, щоб дізнатися про вступ до Духовного центру, створення нової громади, проведення обряду, свята, лекції чи зустрічі.</p>
            </div>
            <a class="figma-button" href="mailto:jaromirdp@gmail.com?subject=Звернення%20з%20сайту%20Рідна%20Віра">Написати Управі</a>
        </section>

        <p class="contact-source-note">Контактні відомості перенесені до нового сайту. Перед поїздкою або участю в заході рекомендуємо попередньо зв’язатися з громадою чи представником.</p>
    </div>
</section>

<script type="application/json" id="community-map-data">@json($communityLocations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)</script>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>document.dispatchEvent(new Event('leaflet:ready'));</script>
@endpush
