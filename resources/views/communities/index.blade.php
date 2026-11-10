@extends('layouts.app')
@section('title', 'Громади Рідної Віри')
@section('description', 'Громади та представництва Духовного центру «Рідна Віра»: знайомство, провідники й контакти.')
@push('styles') @include('communities.styles') @endpush
@section('content')
<section class="community-directory">
    <div class="figma-container">
        <nav class="community-profile__nav" aria-label="Навігаційний шлях"><a href="{{ route('home') }}">Головна</a><a href="{{ route('contact') }}#communities-map-title">Громади на мапі →</a></nav>
        <h1>Громади та представництва</h1>
        <p class="community-directory__intro">Рідна Віра живе в громадах. Знайомтеся з осередками нашого Духовного центру, знаходьте контакти провідників і долучайтеся до спільного кола.</p>
        <div class="community-directory__grid">
            @forelse ($communities as $community)
                <article class="community-summary">
                    <a class="community-summary__image" href="{{ url($community->path) }}" aria-label="{{ $community->title }}">
                        @if($community->image)
                            @include('partials.publication-image', ['visual' => \App\Support\ContentImages::visual($community->image), 'imageAlt' => $community->title])
                        @else
                            <img src="{{ asset('assets/brand/image-placeholder.svg') }}" width="1600" height="900" alt="" loading="lazy" decoding="async">
                        @endif
                    </a>
                    <div class="community-summary__body">
                        <p class="community-profile-location">{{ $community->community['city'] ?? '' }} · {{ $community->community['region'] ?? '' }}</p>
                        <h2>{{ $community->title }}</h2>
                        <p>{{ $community->excerpt }}</p>
                        <a href="{{ url($community->path) }}">Про громаду →</a>
                    </div>
                </article>
            @empty
                <p>Відомості про громади готуються. Контакти можна знайти <a href="{{ route('contact') }}#communities-map-title">на мапі громад</a>.</p>
            @endforelse
        </div>
    </div>
</section>
@endsection
