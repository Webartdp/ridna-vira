<nav class="shop-breadcrumbs" aria-label="Хлібні крихти">
    <ol>
        @foreach ($trail as $crumb)
            <li>
                @unless ($loop->first)<span aria-hidden="true">›</span>@endunless
                @if ($loop->last)
                    <span aria-current="page">{{ $crumb['name'] }}</span>
                @else
                    <a href="{{ $crumb['path'] }}">{{ $crumb['name'] }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
