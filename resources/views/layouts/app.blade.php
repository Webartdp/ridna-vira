<!doctype html>
<html lang="uk" prefix="og: https://ogp.me/ns# article: https://ogp.me/ns/article#">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1c0803">
    @if(request()->routeIs('home'))
    <meta name="google-site-verification" content="HIs_jY0tZTaQfyGkUDXj6tzR4mqu9J9SgQommjWZcio" />
    @endif
    @include('partials.site-icons')
    <title>@yield('title', 'Рідна Віра — Духовний центр')</title>
    <meta name="description" content="@yield('description', 'Офіційний портал Духовного центру Рідна Віра')">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="@yield('body-class')">
<div class="announcement" aria-label="Оголошення">
    <div class="announcement__track" data-ticker-track>
        <span>{{ \App\Models\SiteSetting::read('announcement', 'Духовний центр «Рідна Віра» — жива традиція українців') }}</span>
        <span>{{ \App\Models\SiteSetting::read('announcement', 'Духовний центр «Рідна Віра» — жива традиція українців') }}</span>
    </div>
</div>

<header class="site-header" data-site-header>
    <nav class="navbar navbar-expand-xl">
        <div class="container-fluid site-header__inner">
            <a class="navbar-brand" href="{{ route('home') }}" aria-label="Рідна Віра — головна">
                <img src="{{ \App\Models\SiteSetting::read('logo') ? Storage::disk('public')->url(\App\Models\SiteSetting::read('logo')) : asset('assets/figma/home/logo.png') }}" alt="Духовний центр Рідна Віра">
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Відкрити меню">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav site-nav ms-auto align-items-xl-center">
                    @foreach(\App\Support\Site::navigation() as $item)
                    <li class="nav-item {{ !empty($item['children']) ? 'dropdown' : '' }}">
                        <a class="nav-link {{ !empty($item['children']) ? 'dropdown-toggle' : '' }} {{ request()->getPathInfo() === $item['url'] ? 'active' : '' }}" href="{{ url($item['url']) }}" @if(!empty($item['children'])) role="button" data-bs-toggle="dropdown" aria-expanded="false" @endif>{{ $item['label'] }}</a>
                        @if(!empty($item['children']))<ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ url($item['url']) }}">{{ $item['label'] }}</a></li>
                            @foreach($item['children'] as $child)<li><a class="dropdown-item" href="{{ url($child['url']) }}">{{ $child['label'] }}</a></li>@endforeach
                        </ul>@endif
                    </li>
                    @endforeach
                    @foreach(json_decode(\App\Models\SiteSetting::read('extra_links', '[]'), true) as $item)<li class="nav-item"><a class="nav-link" href="{{ url($item['url']) }}">{{ $item['label'] }}</a></li>@endforeach
                </ul>

                <div class="header-actions">
                    <a href="{{ auth()->check() ? route('account.index') : route('login') }}" aria-label="{{ auth()->check() ? 'Особистий кабінет' : 'Увійти до кабінету' }}" title="Особистий кабінет" style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;flex-shrink:0">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 22v-3a8 8 0 0 1 16 0v3"/></svg>
                    </a>

                    <a class="basket-link" href="{{ url('/koshyk') }}" aria-label="Кошик, {{ array_sum(session('cart', [])) }} товарів">
                        <img class="basket-link__image" src="{{ asset('assets/figma/home/basket.png') }}" alt="">
                        <span class="basket-link__badge">
                            <img src="{{ asset('assets/figma/home/star.svg') }}" alt="">
                            <b>{{ array_sum(session('cart', [])) }}</b>
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </nav>
</header>

<main>@yield('content')</main>

<footer class="site-footer">
    <div class="site-footer__ornament">
        <img src="{{ asset('assets/figma/home/ornament.png') }}" alt="">
    </div>

    <nav class="footer-nav" aria-label="Меню в підвалі">
        @foreach(\App\Support\Site::navigation() as $item)<a href="{{ url($item['url']) }}">{{ $item['label'] }}</a>@endforeach
        @foreach(json_decode(\App\Models\SiteSetting::read('extra_links', '[]'), true) as $item)<a href="{{ url($item['url']) }}">{{ $item['label'] }}</a>@endforeach
    </nav>

    <div class="footer-partners" aria-label="Ресурси Рідної Віри">
        @foreach(json_decode(\App\Models\SiteSetting::read('partners', json_encode(config('site_partners'), JSON_UNESCAPED_UNICODE)), true) as $partner)
        <a class="footer-partner footer-partner--{{ $partner['style'] }}" href="{{ url($partner['url']) }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $partner['name'] }}">
            <img src="{{ asset($partner['image']) }}" alt="{{ $partner['name'] }}" loading="lazy" decoding="async">
        </a>
        @endforeach
    </div>

    <div class="footer-utilities">
        <div class="footer-payments" aria-label="Підтримувані способи оплати">
            <span class="payment-mark" title="Visa" role="img" aria-label="Visa">
                <img src="{{ asset('assets/icons/payment/visa.svg') }}" alt="Visa">
            </span>
            <span class="payment-mark" title="Mastercard" role="img" aria-label="Mastercard">
                <img src="{{ asset('assets/icons/payment/mastercard.svg') }}" alt="Mastercard">
            </span>
            <span class="payment-mark payment-mark--apple" title="Apple Pay" role="img" aria-label="Apple Pay">
                <img src="{{ asset('assets/icons/payment/apple-pay.svg') }}" alt="Apple Pay">
            </span>
        </div>

        <div class="footer-socials" aria-label="Соціальні мережі">
            <span class="footer-social-icon" title="Facebook" role="img" aria-label="Facebook">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.6 22v-8h2.7l.4-3.1h-3.1V8.9c0-.9.3-1.5 1.6-1.5h1.7V4.6c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.1H7.4V14h2.8v8h3.4Z"/></svg>
            </span>
            <span class="footer-social-icon" title="Instagram" role="img" aria-label="Instagram">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.2 2h9.6A5.2 5.2 0 0 1 22 7.2v9.6a5.2 5.2 0 0 1-5.2 5.2H7.2A5.2 5.2 0 0 1 2 16.8V7.2A5.2 5.2 0 0 1 7.2 2Zm0 1.9a3.3 3.3 0 0 0-3.3 3.3v9.6a3.3 3.3 0 0 0 3.3 3.3h9.6a3.3 3.3 0 0 0 3.3-3.3V7.2a3.3 3.3 0 0 0-3.3-3.3H7.2Zm10.1 1.4a1.2 1.2 0 1 1 0 2.4 1.2 1.2 0 0 1 0-2.4ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 1.9a3.1 3.1 0 1 0 0 6.2 3.1 3.1 0 0 0 0-6.2Z"/></svg>
            </span>
            <span class="footer-social-icon" title="Telegram" role="img" aria-label="Telegram">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21.5 3.4-3.1 16.2c-.2 1.1-.9 1.4-1.8.9l-4.8-3.5-2.3 2.2c-.3.3-.5.5-1 .5l.3-4.9 8.9-8c.4-.3-.1-.5-.6-.2L6.1 13.5l-4.7-1.5c-1-.3-1-1 .2-1.5L20 3.4c.9-.3 1.7.2 1.5 0Z"/></svg>
            </span>
        </div>
    </div>

    <div class="footer-rule"><img src="{{ asset('assets/figma/home/footer-line.svg') }}" alt=""></div>
    <p class="footer-copy">© 2006–{{ date('Y') }} {{ \App\Models\SiteSetting::read('footer_text', 'РІДНА ВІРА — Всі права захищені') }} — <a href="{{ url('/umovy-kramnytsi') }}">Умови та конфіденційність</a></p>
</footer>
@stack('scripts')
</body>
</html>
