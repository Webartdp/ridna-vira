@extends('layouts.app')

@section('title', $holiday['name'].' — Рідна Віра')
@section('description', ($holiday['description'] ?? $holiday['name']).' — календарне свято Рідної Віри.')

@section('content')
<section class="inner-hero inner-hero--faith" aria-labelledby="holiday-page-title">
    <div class="inner-hero__overlay"></div>
    <div class="inner-hero__content">
        <h1 id="holiday-page-title">{{ $holiday['name'] }}</h1>
        <nav class="inner-breadcrumbs" aria-label="Навігаційний шлях">
            <a href="{{ route('home') }}">Головна</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('faith') }}">Рідна Віра</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('faith.calendar') }}">Календар свят</a>
            <span aria-hidden="true">/</span>
            <span>{{ $holiday['name'] }}</span>
        </nav>
    </div>
</section>

<section class="holiday-page">
    <div class="figma-container">
        <article class="holiday-sheet">
            <header class="holiday-sheet__header">
                <div class="holiday-date-card" aria-label="Дата свята">
                    <strong>{{ $holiday['day'] }}</strong>
                    <span>{{ $month }}</span>
                </div>

                <div class="holiday-sheet__heading">
                    <span class="holiday-sheet__eyebrow">Коло Свароже</span>
                    <h2>{{ $holiday['name'] }}</h2>
                    @if (!empty($holiday['description']))
                        <p>{{ $holiday['description'] }}</p>
                    @endif
                </div>
            </header>

            @if ($content !== null && trim($content) !== '')
                <div class="holiday-sheet__content">
                    {!! $content !!}
                </div>
            @elseif (!empty($holiday['description']))
                <div class="holiday-sheet__content holiday-sheet__content--short">
                    <p>{{ $holiday['description'] }}</p>
                </div>
            @endif

            <footer class="holiday-sheet__footer">
                <a class="document-back-link" href="{{ route('faith.calendar') }}">← До календаря свят</a>
            </footer>
        </article>
    </div>
</section>
@endsection
