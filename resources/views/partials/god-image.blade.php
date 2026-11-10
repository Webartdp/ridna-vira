@if (!empty($god['_cms_image_managed']))
    @include('partials.publication-image', ['visual' => $god['image'], 'imageAlt' => $imageAlt ?? '', 'placeholder' => $god['title'] ?? 'Рідна Віра'])
@else
@php
    $imageSlug = (string) ($slug ?? $god['slug'] ?? 'bog');
    $variants = [];
    foreach ([320 => 'card-320.webp', 640 => 'card-640.webp', 960 => 'portrait.webp'] as $width => $filename) {
        $relative = 'assets/gods/'.$imageSlug.'/'.$filename;
        if (is_file(public_path($relative))) {
            $variants[$width] = asset($relative).'?v='.filemtime(public_path($relative));
        }
    }
    $imageSource = $variants[640] ?? $variants[960] ?? null;
    $imageSrcset = implode(', ', array_map(static fn ($width, $url) => $url.' '.$width.'w', array_keys($variants), array_values($variants)));
    if ($imageSource === null) {
        $fallback = 'assets/gods/'.$imageSlug.'/portrait.png';
        $imageSource = is_file(public_path($fallback)) ? asset($fallback) : ($god['image'] ?? asset('assets/gods/'.$imageSlug.'/portrait.svg'));
    }
@endphp
<img src="{{ $imageSource }}"
     @if ($imageSrcset !== '') srcset="{{ $imageSrcset }}" sizes="{{ $sizes }}" @endif
     width="640" height="800" alt="{{ $imageAlt ?? '' }}"
     loading="{{ $imageLoading ?? 'lazy' }}" decoding="async" fetchpriority="{{ $imagePriority ?? 'auto' }}">
@endif
