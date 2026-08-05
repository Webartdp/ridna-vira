@extends('layouts.app')

@section('title', 'Про центр — Рідна Віра')
@section('description', 'Духовний центр Рідна Віра: управління, зв’язок, відомості про центр та світлини громади.')

@section('content')
<section class="inner-hero inner-hero--about" aria-labelledby="about-page-title">
    <div class="inner-hero__overlay"></div>
    <div class="inner-hero__content">
        <h1 id="about-page-title">Про центр</h1>
        <nav class="inner-breadcrumbs" aria-label="Навігаційний шлях">
            <a href="{{ route('home') }}">Головна</a>
            <span aria-hidden="true">/</span>
            <span>Про центр</span>
        </nav>
    </div>
</section>

<section class="about-page">
    <div class="figma-container">
        <nav class="about-links" aria-label="Розділи про центр">
            <a class="about-link-card" href="{{ url('/pro-tsentr/upravlinnia') }}">Управління</a>
            <a class="about-link-card" href="{{ url('/zviazok') }}">Зв’язок</a>
            <a class="about-link-card" href="#about-center">Про нас</a>
            <a class="about-link-card" href="{{ url('/pro-tsentr/entsyklopediia') }}">Енциклопедія</a>
        </nav>

        <div class="about-copy" id="about-center">
            <p>Духовний центр «Рідна Віра» об’єднує громади, духовних провідників і всіх, хто прагне пізнавати та зберігати українську духовну традицію. У своїй діяльності ми спираємося на живий зв’язок із Рідними Богами, шанування Предків, відповідальність перед Родом і гармонійне співжиття з Природою.</p>
            <p>Центр розвиває духовну освіту, підтримує громади, проводить обряди й святодійства, готує навчальні матеріали та створює простір для спілкування людей Рідної Віри. Наше завдання — зробити традицію зрозумілою, доступною й живою для сучасного українця.</p>
        </div>
    </div>

    <section class="about-gallery" aria-labelledby="about-gallery-title" data-about-gallery>
        <h2 id="about-gallery-title">Світлини</h2>

        <div class="about-gallery__viewport">
            <div class="about-gallery__track" data-about-gallery-track>
                <figure class="about-gallery__slide">
                    <img src="{{ asset('assets/figma/about/gallery-1.png') }}" alt="Святодійство громади Рідної Віри" loading="lazy">
                </figure>
                <figure class="about-gallery__slide is-active">
                    <img src="{{ asset('assets/figma/about/gallery-2.png') }}" alt="Спільна світлина учасників громади" loading="lazy">
                </figure>
                <figure class="about-gallery__slide">
                    <img src="{{ asset('assets/figma/about/gallery-3.png') }}" alt="Учасники святкування Рідної Віри" loading="lazy">
                </figure>
            </div>
        </div>

        <div class="about-gallery__controls">
            <button type="button" class="about-gallery__button about-gallery__button--prev" data-about-gallery-prev aria-label="Попередня світлина">
                <img src="{{ asset('assets/figma/about/arrow-right.svg') }}" alt="">
            </button>
            <button type="button" class="about-gallery__button" data-about-gallery-next aria-label="Наступна світлина">
                <img src="{{ asset('assets/figma/about/arrow-right.svg') }}" alt="">
            </button>
        </div>
    </section>
</section>
@endsection
