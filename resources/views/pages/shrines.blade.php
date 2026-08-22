@extends('layouts.app')

@section('title', 'Святині — Рідна Віра')
@section('description', 'Локально збережений розділ святинь Духовного центру Рідна Віра.')

@php
    $regionsCount = count($regions ?: collect($shrines)->pluck('region')->filter()->unique()->values()->all());
@endphp

@section('content')
<section class="inner-hero inner-hero--faith" aria-labelledby="shrines-page-title">
    <div class="inner-hero__overlay"></div>
    <div class="inner-hero__content">
        <h1 id="shrines-page-title">Святині</h1>
        <nav class="inner-breadcrumbs" aria-label="Навігаційний шлях">
            <a href="{{ route('home') }}">Головна</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('faith') }}">Рідна Віра</a>
            <span aria-hidden="true">/</span>
            <span>Святині</span>
        </nav>
    </div>
</section>

<section class="faith-books-page">
    <div class="figma-container">
        <header class="faith-books-intro">
            <div>
                <span class="faith-books-intro__eyebrow">Священні місця</span>
                <h2>Святині Рідної Землі</h2>
                <p>Місця сили, давні святилища, городища, могили, печери та природні святині, збережені в локальному архіві сайту.</p>
            </div>

            <dl class="faith-books-summary" aria-label="Підсумок архіву святинь">
                <div>
                    <dt>{{ count($shrines) }}</dt>
                    <dd>святинь</dd>
                </div>
                <div>
                    <dt>{{ $regionsCount }}</dt>
                    <dd>областей</dd>
                </div>
                <div>
                    <dt>{{ $importedAt ? 'OK' : '—' }}</dt>
                    <dd>імпорт</dd>
                </div>
            </dl>
        </header>

        <ol class="faith-books-list" aria-label="Список святинь">
            @forelse ($shrines as $shrine)
                <li class="faith-book-row">
                    <span class="faith-book-row__number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>

                    <div class="faith-book-row__cover" aria-hidden="true"></div>

                    <div class="faith-book-row__content">
                        <span class="faith-book-row__category">{{ $shrine['region'] ?? 'Святиня' }}</span>
                        <h3>{{ $shrine['title'] }}</h3>
                        <p class="faith-book-row__meta">
                            @if (!empty($shrine['characters']))
                                {{ number_format($shrine['characters'], 0, ',', ' ') }} знаків
                            @else
                                Локальний матеріал
                            @endif
                            @if (!empty($shrine['images']))
                                · {{ $shrine['images'] }} зобр.
                            @endif
                        </p>
                    </div>

                    <div class="faith-book-row__formats" aria-label="Дії зі святинею">
                        <a class="faith-book-row__format" href="{{ route('faith.shrines.show', ['shrine' => $shrine['slug']]) }}">Читати</a>
                    </div>
                </li>
            @empty
                <li class="faith-book-row">
                    <span class="faith-book-row__number">00</span>
                    <div class="faith-book-row__cover" aria-hidden="true"></div>
                    <div class="faith-book-row__content">
                        <span class="faith-book-row__category">Архів</span>
                        <h3>Святині ще не імпортовано</h3>
                        <p>Після перенесення тут з’явиться локальний список матеріалів.</p>
                    </div>
                </li>
            @endforelse
        </ol>
    </div>
</section>
@endsection
