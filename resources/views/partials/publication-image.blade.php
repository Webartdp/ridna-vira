@if (!empty($visual))
    <img src="{{ asset(ltrim($visual['src'], '/')) }}"
         @if (!empty($visual['variants'])) srcset="{{ collect($visual['variants'])->map(fn ($variant) => asset(ltrim($variant['src'], '/')).' '.$variant['width'].'w')->implode(', ') }}" sizes="{{ $sizes ?? '(min-width: 1000px) 500px, calc(100vw - 40px)' }}" @endif
         width="{{ $visual['width'] }}" height="{{ $visual['height'] }}" alt="{{ $imageAlt ?? '' }}" loading="lazy" decoding="async">
@else
    <span class="publication-placeholder" aria-hidden="true"><span>{{ $placeholder ?? 'Рідна Віра' }}</span></span>
@endif
