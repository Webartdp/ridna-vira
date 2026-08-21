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
            <h2>Свята протягом року</h2>
            <p>Календар упорядковано за державним григоріанським стилем. У ньому поєднано сонячне коло року, сезонні переходи, вшанування Рідних Богів і Предків, а також традиційні обрядові дати.</p>
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
