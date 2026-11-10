@extends('layouts.app')
@section('title', $community->title.' — Рідна Віра')
@section('description', $community->excerpt ?? '')
@push('styles') @include('communities.styles') @include('partials.ritual-styles') @endpush
@section('content')
@php($details = $community->community ?? [])
<article class="community-profile">
    <div class="figma-container">
        <nav class="community-profile__nav" aria-label="Навігаційний шлях"><a href="{{ route('home') }}">Головна</a><a href="{{ route('communities.index') }}">Громади</a></nav>
        <p class="community-profile-location">{{ $details['city'] ?? '' }} · {{ $details['region'] ?? '' }}</p>
        <h1>{{ $community->title }}</h1>
        <div class="community-profile__grid">
            <div>
                @if ($community->image)
                    <figure class="community-profile__photo">@include('partials.publication-image', ['visual' => \App\Support\ContentImages::visual($community->image), 'imageAlt' => $community->title, 'sizes' => '(min-width: 1000px) 760px, 90vw'])</figure>
                @endif
                <div class="community-profile__body">{!! \App\Support\CmsHtml::render($community->body ?? '') !!}</div>
            </div>
            <aside class="community-profile__contacts" aria-labelledby="community-contacts-title">
                <h2 id="community-contacts-title">Зв’язок із громадою</h2>
                @if(!empty($details['person']))<p><strong>{{ $details['person'] }}</strong><br>{{ $details['role'] ?? '' }}</p>@endif
                @if(!empty($details['address']))<p>{{ $details['address'] }}</p>@endif
                @foreach($details['phones'] ?? [] as $phone)<a href="tel:{{ preg_replace('/[^+0-9]/', '', $phone) }}">{{ $phone }}</a>@endforeach
                @foreach($details['emails'] ?? [] as $email)<a href="mailto:{{ $email }}">{{ $email }}</a>@endforeach
                <a href="{{ route('contact') }}#communities-map-title">Громади на мапі →</a>
            </aside>
        </div>
    </div>
</article>
@endsection
