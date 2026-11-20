@if ($links)
    <nav class="shop-policy-links" aria-label="{{ $label }}">
        @foreach ($links as $path => $title)
            <a href="{{ url($path) }}" @if (! empty($newTab)) target="_blank" rel="noopener" @endif>{{ $title }}</a>
        @endforeach
    </nav>
@endif
