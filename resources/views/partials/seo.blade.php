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
<meta property="og:image:alt" content="{{ $seo['seoTitle'] }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seo['seoTitle'] }}">
<meta name="twitter:description" content="{{ $seo['description'] }}">
<meta name="twitter:image" content="{{ $seo['image'] }}">
@unless($seo['noindex'])
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@graph' => $seo['graph']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
@endunless
