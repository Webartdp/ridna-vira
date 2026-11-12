@extends('layouts.app')

@section('title', 'Зв’язок — Рідна Віра')
@section('description', 'Карта громад, представництв і контакти проводу Духовного центру Рідна Віра.')

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
@endpush

@php
    $communityLocations = \App\Support\CommunityMap::locations();
    $communityCircles = array_column(\App\Support\CommunityMap::circles(), 'label', 'id');
    $locationsByCircle = collect($communityLocations)->groupBy('circle');

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

        <!-- cms-live:communities -->
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
                        @foreach ($communityCircles as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="community-map-types" aria-label="Тип осередку">
                    <button class="community-map-filter is-active" type="button" data-community-map-type="all">Усі</button>
                    <button class="community-map-filter" type="button" data-community-map-type="community">Громади</button>
                    <button class="community-map-filter" type="button" data-community-map-type="representation">Представництва</button>
                </div>
            </div>

            <div class="community-map-shell">
                <div class="community-map-canvas"
                     data-community-map-canvas
                     data-tile-url="{{ config('community_map.tile_url') }}"
                     data-tile-attribution="{{ config('community_map.attribution') }}"
                     data-tile-max-zoom="{{ config('community_map.max_zoom') }}"
                     aria-label="Інтерактивна мапа громад і представництв Рідної Віри"></div>

                <aside class="community-map-directory" aria-label="Перелік громад і представництв">
                    <div class="community-map-directory__head">
                        <span>Знайдено осередків</span>
                        <strong><b data-community-map-count>{{ $communityEntryCount }}</b> у переліку</strong>
                    </div>

                    <div class="community-map-locations">
                        @foreach ($communityCircles as $id => $label)
                            @if ($locationsByCircle->has($id))
                                <section data-map-circle-group aria-label="{{ $label }}">
                                    <h3 class="community-map-circle-heading">{{ $label }}</h3>
                                    @foreach ($locationsByCircle->get($id, []) as $location)
                                        <article class="community-location" data-map-location="{{ $location['id'] }}">
                                            <button class="community-location__button" type="button" data-map-location-button>
                                                <span>
                                                    <span class="community-location__city">{{ $location['city'] }}</span>
                                                    <span class="community-location__meta">{{ $location['region'] }} · {{ $location['circleLabel'] }}</span>
                                                </span>
                                                <span class="community-location__count">{{ count($location['entries']) }}</span>
                                            </button>

                                            <div class="community-location__entries">
                                                @foreach ($location['entries'] as $entryIndex => $entry)
                                                    <div class="community-location__entry" data-map-entry="{{ $entryIndex }}">
                                                        <b>{{ $entry['title'] }}</b>
                                                        <small>{{ collect([$entry['role'], $entry['person']])->filter()->implode(' — ') }}</small>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </article>
                                    @endforeach
                                </section>
                            @endif
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

        <!-- /cms-live:communities -->
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

<script type="application/json" id="community-map-data">@json($communityLocations, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)</script>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>document.dispatchEvent(new Event('leaflet:ready'));</script>
@endpush
