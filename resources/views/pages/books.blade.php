@extends('layouts.app')

@section('title', 'Книги — Рідна Віра')
@section('description', 'Книги Духовного центру Рідна Віра: основи віри, календар, Рідні Боги, обряди, молитви, пісні та дослідження.')

@php
    $books = config('faith_books.books', []);
    $featuredBooks = array_slice($books, 0, 3);
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
                <p>Добірка видань Духовного центру «Рідна Віра»: основи віровчення, календар, Рідні Боги, святині, молитви, пісні, обряди та дослідницькі праці. Матеріали подані для читання, друку й подальшого перенесення в нашу систему керування контентом.</p>
            </div>

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
        </header>

        @if ($featuredBooks !== [])
            <div class="faith-books-featured" aria-label="Основні книги">
                @foreach ($featuredBooks as $book)
                    <article class="faith-book faith-book--featured">
                        <div class="faith-book__cover" aria-hidden="true">
                            @if (!empty($book['cover']))
                                <img src="{{ $book['cover'] }}" alt="" loading="lazy" onerror="this.remove()">
                            @endif
                        </div>
                        <div class="faith-book__body">
                            <span class="faith-book__category">{{ $book['category'] }}</span>
                            <h3>{{ $book['title'] }}</h3>
                            <p class="faith-book__meta">{{ $book['author'] }} · {{ $book['year'] }} · {{ $book['pages'] }} с.</p>
                            <p>{{ $book['description'] }}</p>
                            <div class="faith-book__formats" aria-label="Формати книги">
                                @foreach (($book['formats'] ?? []) as $format => $url)
                                    <a class="faith-book__format faith-book__format--{{ $format }}" href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ strtoupper($format) }}</a>
                                @endforeach
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        @if ($categories !== [])
            <nav class="faith-books-categories" aria-label="Категорії книг">
                @foreach ($categories as $category)
                    <a href="#books-{{ Str::slug($category) }}">{{ $category }}</a>
                @endforeach
            </nav>
        @endif

        <div class="faith-books-groups">
            @foreach ($categories as $category)
                @php
                    $categoryBooks = array_values(array_filter($books, fn ($book) => ($book['category'] ?? null) === $category));
                @endphp

                <section class="faith-books-group" id="books-{{ Str::slug($category) }}" aria-labelledby="books-heading-{{ Str::slug($category) }}">
                    <header class="faith-books-group__header">
                        <h3 id="books-heading-{{ Str::slug($category) }}">{{ $category }}</h3>
                        <span>{{ count($categoryBooks) }}</span>
                    </header>

                    <div class="faith-books-grid">
                        @foreach ($categoryBooks as $book)
                            <article class="faith-book">
                                <div class="faith-book__cover" aria-hidden="true">
                                    @if (!empty($book['cover']))
                                        <img src="{{ $book['cover'] }}" alt="" loading="lazy" onerror="this.remove()">
                                    @endif
                                </div>

                                <div class="faith-book__body">
                                    <p class="faith-book__meta">{{ $book['author'] }} · {{ $book['year'] }} · {{ $book['pages'] }} с.</p>
                                    <h4>{{ $book['title'] }}</h4>
                                    <p>{{ $book['description'] }}</p>
                                    <div class="faith-book__formats" aria-label="Формати книги">
                                        @foreach (($book['formats'] ?? []) as $format => $url)
                                            <a class="faith-book__format faith-book__format--{{ $format }}" href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ strtoupper($format) }}</a>
                                        @endforeach
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <footer class="faith-books-footer">
            <a class="document-back-link" href="{{ route('faith') }}">← До розділу «Рідна Віра»</a>
        </footer>
    </div>
</section>
@endsection
