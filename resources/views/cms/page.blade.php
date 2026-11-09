@extends('layouts.app')
@section('title', $page->title.' — Рідна Віра')
@section('description', $page->excerpt ?? '')
@push('styles') @include('shop.styles') @include('partials.ritual-styles') @endpush
@section('content')
<article class="shop"><p><a href="{{ url('/') }}">Рідна Віра</a> / {{ \App\Models\ContentPage::sections()[$page->section] ?? 'Матеріали' }}</p><h1>{{ $page->title }}</h1>
@if($page->image)<img src="{{ Storage::disk('public')->url($page->image) }}" alt="{{ $page->title }}" width="1200" height="800" style="max-width:100%;height:auto;margin-bottom:28px">@endif
<div class="shop-panel prose">{!! \App\Support\CmsHtml::render($page->body ?? '') !!}</div>
@if($page->video_url && preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{11})(?:[?&#/]|$)~', $page->video_url, $video))<iframe style="width:100%;aspect-ratio:16/9;border:0;margin-top:24px" src="https://www.youtube-nocookie.com/embed/{{ $video[1] }}" title="{{ $page->title }} — відео" loading="lazy" allowfullscreen></iframe>@endif
</article>
@endsection
