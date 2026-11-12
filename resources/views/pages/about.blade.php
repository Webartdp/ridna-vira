@extends('layouts.app')

@section('title', 'Про центр — Рідна Віра')
@section('description', 'Духовний центр Рідна Віра: управління, зв’язок, Академія та світлини громади.')

@section('content')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@6.1.14/dist/fancybox/fancybox.css">

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
            <a class="about-link-card" href="{{ route('about.management') }}">Управління</a>
            <a class="about-link-card" href="{{ route('contact') }}">Зв’язок</a>
            <a class="about-link-card" href="https://ridnovir.in.ua/" target="_blank" rel="noopener noreferrer">Академія</a>
        </nav>

        <div class="about-copy" id="about-center">
            <p>Духовний центр «Рідна Віра» об’єднує громади, духовних провідників і всіх, хто прагне пізнавати та зберігати українську духовну традицію. У своїй діяльності ми спираємося на живий зв’язок із Рідними Богами, шанування Предків, відповідальність перед Родом і гармонійне співжиття з Природою.</p>
            <p>Центр розвиває духовну освіту, підтримує громади, проводить обряди й святодійства, готує навчальні матеріали та створює простір для спілкування людей Рідної Віри. Наше завдання — зробити традицію зрозумілою, доступною й живою для сучасного українця.</p>
        </div>
    </div>

    <!-- cms-live:about-gallery -->
    @include('partials.about-gallery', ['photos' => \App\Support\AboutGallery::photos()])
    <!-- /cms-live:about-gallery -->
</section>

<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@6.1.14/dist/fancybox/fancybox.umd.js"></script>
<script>
    window.Fancybox?.bind('[data-fancybox="about-center"]', {});
</script>
@endsection
