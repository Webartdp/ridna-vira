<title>{{ $seo['seoTitle'] }}</title>
<meta name="description" content="{{ $seo['description'] }}">
<meta name="robots" content="{{ $seo['noindex'] ? 'noindex, follow' : 'index, follow, max-image-preview:large' }}">
<link rel="canonical" href="{{ $seo['canonical'] }}">
<meta property="og:locale" content="uk_UA">
<meta property="og:site_name" content="Рідна Віра">
<meta property="og:type" content="{{ $seo['type'] === 'article' ? 'article' : 'website' }}">
<meta property="og:title" content="{{ $seo['seoTitle'] }}">
<meta property="og:description" content="{{ $seo['description'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">
<meta property="og:image" content="{{ $seo['image'] }}">
<meta property="og:image:url" content="{{ $seo['image'] }}">
@if(str_starts_with($seo['image'], 'https://'))
<meta property="og:image:secure_url" content="{{ $seo['image'] }}">
@endif
@foreach(['type', 'width', 'height'] as $property)
@if(isset($seo['imageInfo'][$property]))
<meta property="og:image:{{ $property }}" content="{{ $seo['imageInfo'][$property] }}">
@endif
@endforeach
<meta property="og:image:alt" content="{{ $seo['seoTitle'] }}">
@if($seo['type'] === 'article')
@if($seo['published'])<meta property="article:published_time" content="{{ $seo['published'] }}">@endif
@if($seo['modified'])<meta property="article:modified_time" content="{{ $seo['modified'] }}">@endif
@if($seo['sectionLabel'])<meta property="article:section" content="{{ $seo['sectionLabel'] }}">@endif
@endif
@if($seo['video'])
<meta property="og:video" content="{{ $seo['video']['url'] }}">
<meta property="og:video:secure_url" content="{{ $seo['video']['url'] }}">
<meta property="og:video:type" content="text/html">
<meta property="og:video:width" content="{{ $seo['video']['width'] }}">
<meta property="og:video:height" content="{{ $seo['video']['height'] }}">
@endif
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seo['seoTitle'] }}">
<meta name="twitter:description" content="{{ $seo['description'] }}">
<meta name="twitter:image" content="{{ $seo['image'] }}">
<meta name="twitter:image:alt" content="{{ $seo['seoTitle'] }}">
@unless($seo['noindex'])
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@graph' => $seo['graph']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
@endunless
