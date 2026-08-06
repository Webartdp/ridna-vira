@extends('layouts.app')

@section('title', 'Рідна Віра — Духовний центр')
@section('description', 'Світогляд, календар свят, Рідні Боги, святині, книги, обряди та молитви Рідної Віри.')

@php
    $faithSections = [
        [
            'title' => 'Календар свят',
            'route' => 'faith.calendar',
            'icon' => 'assets/figma/faith/calendar.png',
        ],
        [
            'title' => 'Боги',
            'route' => 'faith.gods',
            'icon' => 'assets/figma/faith/gods.png',
        ],
        [
            'title' => 'Святині',
            'route' => 'faith.shrines',
            'icon' => 'assets/figma/faith/shrines.png',
        ],
        [
            'title' => 'Книги',
            'route' => 'faith.books',
            'icon' => 'assets/figma/faith/books.png',
        ],
        [
            'title' => 'Обряди',
            'route' => 'faith.rituals',
            'icon' => 'assets/figma/faith/rituals.png',
        ],
        [
            'title' => 'Молитви',
            'route' => 'faith.prayers',
            'icon' => 'assets/figma/faith/prayers.png',
        ],
    ];
@endphp

@section('content')
<section class="inner-hero inner-hero--faith" aria-labelledby="faith-page-title">
    <div class="inner-hero__overlay"></div>
    <div class="figma-container inner-hero__content">
        <h1 id="faith-page-title">Рідна Віра</h1>
        <nav aria-label="Хлібні крихти">
            <a href="{{ route('home') }}">Головна</a>
            <span>/</span>
            <span>Рідна Віра</span>
        </nav>
    </div>
</section>

<section class="faith-page">
    <div class="figma-container">
        <nav class="faith-sections" aria-label="Розділи Рідної Віри">
            @foreach ($faithSections as $section)
                <a class="faith-section-card" href="{{ route($section['route']) }}">
                    <img src="{{ asset($section['icon']) }}" alt="" width="98" height="98">
                    <strong>{{ $section['title'] }}</strong>
                </a>
            @endforeach
        </nav>

        <article class="faith-copy">
            <p>Рідна Віра — це жива духовна традиція українського народу, що єднає людину з Родом, Рідними Богами, Предками, рідною Землею та природним колом життя. Вона ґрунтується на народному Звичаї, шануванні спадщини Русі-України та відповідальності кожного перед родиною, громадою і майбутніми поколіннями.</p>

            <p>У центрі світогляду Рідної Віри — єдність усього сущого. Людина не стоїть над Природою і не відділена від неї, а є частиною живого Всесвіту. Рідні Боги постають багатоманітними проявами божественного ладу, природних сил і духовних якостей, через які пізнається єдиний та багатопроявний Род.</p>

            <p>Коло Свароже поєднує календарні свята з рухом Сонця, зміною пір року, працею на Землі та пам’яттю Предків. Через святодії людина усвідомлює своє місце у безперервному колі народження, розвитку, зрілості, відходу та нового продовження життя у Роді.</p>

            <p>Обряди супроводжують найважливіші події людського життя: народження, ім’янаречення, посвяту, створення родини, благословення справи, поховання та поминання. Їхня мета — не формальне виконання дій, а відновлення гармонії між людиною, родиною, громадою, Предками й Рідними Богами.</p>

            <p>Книги, молитви, перекази, символи та дослідження святинь допомагають пізнавати світогляд Предків і осмислювати його в сучасному житті. Рідна Віра не відриває людину від сьогодення, а навчає діяти свідомо, берегти культуру, мову, природу та духовну самобутність українського народу.</p>

            <p>Оберіть потрібний розділ вище, щоб ознайомитися з календарем свят, Рідними Богами, святинями, книгами, обрядами та молитвами Духовного центру «Рідна Віра».</p>
        </article>
    </div>
</section>
@endsection
