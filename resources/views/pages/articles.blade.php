@extends('layouts.app')

@section('title', 'Статті — Рідна Віра')
@section('description', 'Локальний архів статей і дослідницьких матеріалів Духовного центру Рідна Віра.')

@php
    $formats = [];
    foreach ($articles as $article) {
        $extension = strtoupper((string) ($article['extension'] ?? ''));
        if ($extension !== '' && !in_array($extension, $formats, true)) {
            $formats[] = $extension;
        }
    }
@endphp

@section('content')
<section class="inner-hero inner-hero--faith" aria-labelledby="articles-page-title">
    <div class="inner-hero__overlay"></div>
    <div class="inner-hero__content">
        <h1 id="articles-page-title">Статті</h1>
        <nav class="inner-breadcrumbs" aria-label="Навігаційний шлях">
            <a href="{{ route('home') }}">Головна</a>
            <span aria-hidden="true">/</span>
            <span>Статті</span>
        </nav>
    </div>
</section>

<section class="faith-books-page">
    <div class="figma-container">
        <header class="faith-books-intro">
            <div>
                <span class="faith-books-intro__eyebrow">Архів</span>
                <h2>Статті та матеріали</h2>
                <p>Локально збережений список статей, досліджень і файлів. Після імпорту матеріали відкриваються та завантажуються з нашого сайту.</p>
            </div>

            <dl class="faith-books-summary" aria-label="Підсумок архіву статей">
                <div>
                    <dt>{{ count($articles) }}</dt>
                    <dd>матеріалів</dd>
                </div>
                <div>
                    <dt>{{ count($formats) }}</dt>
                    <dd>форматів</dd>
                </div>
                <div>
                    <dt>{{ $importedAt ? 'OK' : '—' }}</dt>
                    <dd>імпорт</dd>
                </div>
            </dl>
        </header>

        <ol class="faith-books-list" aria-label="Список статей">
            @forelse ($articles as $article)
                <li class="faith-book-row">
                    <span class="faith-book-row__number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>

                    <div class="faith-book-row__cover" aria-hidden="true"></div>

                    <div class="faith-book-row__content">
                        <span class="faith-book-row__category">{{ strtoupper($article['extension'] ?? 'HTML') }}</span>
                        <h3>{{ $article['title'] }}</h3>
                        <p class="faith-book-row__meta">
                            @if (!empty($article['size']))
                                {{ number_format($article['size'] / 1024, 0, ',', ' ') }} КБ
                            @else
                                Локальний матеріал
                            @endif
                        </p>
                    </div>

                    <div class="faith-book-row__formats" aria-label="Дії з матеріалом">
                        <a class="faith-book-row__format" href="{{ route('articles.show', ['article' => $article['slug']]) }}">{{ ($article['extension'] ?? '') === 'html' ? 'Читати' : 'Перегляд' }}</a>
                        <a class="faith-book-row__format faith-book-row__format--docx" href="{{ route('articles.download', ['article' => $article['slug']]) }}" download>Файл</a>
                    </div>
                </li>
            @empty
                <li class="faith-book-row">
                    <span class="faith-book-row__number">00</span>
                    <div class="faith-book-row__cover" aria-hidden="true"></div>
                    <div class="faith-book-row__content">
                        <span class="faith-book-row__category">Архів</span>
                        <h3>Статті ще не імпортовано</h3>
                        <p>Запустіть імпортер статей на сервері, і тут з’явиться локальний список матеріалів.</p>
                    </div>
                </li>
            @endforelse
        </ol>
    </div>
</section>
@endsection
