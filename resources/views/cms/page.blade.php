@extends('layouts.app')
@section('title', $page->title.' — Рідна Віра')
@section('description', $page->excerpt ?? '')
@push('styles') @include('shop.styles') @include('partials.ritual-styles') @endpush
@section('content')
@php($book = $page->section === 'books' ? \App\Support\BookLibrary::find(basename($page->path)) : null)
<article class="shop"><p><a href="{{ url('/') }}">Рідна Віра</a> / {{ \App\Models\ContentPage::sections()[$page->section] ?? 'Матеріали' }}</p><h1>{{ $page->title }}</h1>
@if($page->image || $book)<figure style="margin:0 0 28px; {{ $book ? 'max-width:280px' : '' }}">@include('partials.publication-image', ['visual' => $page->image ? \App\Support\ContentImages::visual($page->image) : ($book['image'] ?? null), 'imageAlt' => $page->title])</figure>@endif
<div class="shop-panel prose">{!! \App\Support\CmsHtml::render($page->body ?? '') !!}</div>
@if($page->section === 'books')
    <p>{{ collect([$book['author'] ?? null, $book['year'] ?? null, !empty($book['pages']) ? $book['pages'].' с.' : null])->filter()->implode(' · ') }}</p>
    <p>@foreach($book['formats'] as $format => $download)<a class="shop-button" href="{{ $download }}">Завантажити {{ strtoupper($format) }}</a> @endforeach</p>
@endif
@if($page->video_url && preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{11})(?:[?&#/]|$)~', $page->video_url, $video))<iframe style="width:100%;aspect-ratio:16/9;border:0;margin-top:24px" src="https://www.youtube-nocookie.com/embed/{{ $video[1] }}" title="{{ $page->title }} — відео" loading="lazy" allowfullscreen></iframe>@endif
</article>
@endsection
