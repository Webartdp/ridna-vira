@extends('layouts.app')

@section('title', 'Книги — Рідна Віра')
@section('description', 'Книги Духовного центру Рідна Віра: основи віри, календар, Рідні Боги, обряди, молитви, пісні та дослідження.')

@php
    $books = \App\Support\BookLibrary::entries();
    $categories = [];
    foreach ($books as $book) {
        $category = $book['category'] ?? null;
        if ($category !== null && !in_array($category, $categories, true)) {
            $categories[] = $category;
        }
    }
@endphp

@section('content')
<section class="inner-hero inner-hero--faith" aria-labelledby="books-page-title">
    <div class="inner-hero__overlay"></div>
    <div class="inner-hero__content">
        <h1 id="books-page-title">Книги</h1>
        <nav class="inner-breadcrumbs" aria-label="Навігаційний шлях">
            <a href="{{ route('home') }}">Головна</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('faith') }}">Рідна Віра</a>
            <span aria-hidden="true">/</span>
            <span>Книги</span>
        </nav>
    </div>
</section>

<section class="faith-books-page">
    <div class="figma-container">
        <header class="faith-books-intro">
            <div>
                <span class="faith-books-intro__eyebrow">Бібліотека</span>
                <h2>Книги Рідної Віри</h2>
                <p>Добірка видань Духовного центру «Рідна Віра»: основи віровчення, календар, Рідні Боги, святині, молитви, пісні, обряди та дослідницькі праці. Матеріали для читання, дослідження та друку.</p>
            </div>

            <!-- cms-live:books-summary -->
            <dl class="faith-books-summary" aria-label="Підсумок бібліотеки">
                <div>
                    <dt>{{ count($books) }}</dt>
                    <dd>видання</dd>
                </div>
                <div>
                    <dt>{{ count($categories) }}</dt>
                    <dd>напрямів</dd>
                </div>
                <div>
                    <dt>PDF</dt>
                    <dd>для друку</dd>
                </div>
            </dl>
            <!-- /cms-live:books-summary -->
        </header>

        <!-- cms-live:books-catalogue -->
        <ol class="faith-books-list" aria-label="Список книг">
            @foreach ($books as $book)
                @php
                    $bookSlug = $book['slug'] ?? \Illuminate\Support\Str::slug((string) ($book['title'] ?? 'knyha'));
                @endphp

                <li class="faith-book-row">
                    <span class="faith-book-row__number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>

                    <div class="faith-book-row__cover" aria-hidden="true">
                        @include('partials.publication-image', ['visual' => $book['image'] ?? null, 'sizes' => '120px', 'imageAlt' => ''])
                    </div>

                    <div class="faith-book-row__content">
                        <span class="faith-book-row__category">{{ $book['category'] }}</span>
                        <h3>@if(!empty($book['url']))<a href="{{ $book['url'] }}">{{ $book['title'] }}</a>@else{{ $book['title'] }}@endif</h3>
                        <p class="faith-book-row__meta">{{ collect([$book['author'] ?? null, $book['year'] ?? null, !empty($book['pages']) ? $book['pages'].' с.' : null])->filter()->implode(' · ') }}</p>
                        <p>{{ $book['description'] }}</p>
                    </div>

                    <div class="faith-book-row__formats" aria-label="Формати книги">
                        @foreach (array_keys($book['formats'] ?? []) as $format)
                            <a class="faith-book-row__format faith-book-row__format--{{ $format }}" href="{{ $book['formats'][$format] }}" download>{{ strtoupper($format) }}</a>
                        @endforeach
                    </div>
                </li>
            @endforeach
        </ol>
        <!-- /cms-live:books-catalogue -->

        <footer class="faith-books-footer">
            <a class="document-back-link" href="{{ route('faith') }}">← До розділу «Рідна Віра»</a>
        </footer>
    </div>
</section>
@endsection
