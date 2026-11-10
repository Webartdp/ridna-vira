<div class="communities-grid">
    @foreach (\App\Support\CommunityDirectory::featured() as $community)
        <a class="community-card" href="{{ url($community->path) }}">
            @if ($community->image)
                @include('partials.publication-image', ['visual' => \App\Support\ContentImages::visual($community->image), 'imageAlt' => $community->title, 'sizes' => '(min-width: 1000px) 320px, (min-width: 600px) 45vw, 90vw'])
            @else
                <img src="{{ asset('assets/communities/sacred-grove-800.webp') }}" width="800" height="267" alt="" loading="lazy" decoding="async">
            @endif
            <span>{{ $community->title }}</span>
        </a>
    @endforeach
</div>
<p><a href="{{ route('communities.index') }}">Дізнатися про громади та представництва →</a></p>
