<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1c0803">
    <title>@yield('title', 'Рідна Віра — Духовний центр')</title>
    <meta name="description" content="@yield('description', 'Офіційний портал Духовного центру Рідна Віра')">
    @vite(['resources/scss/app.scss', 'resources/scss/corrections.scss', 'resources/js/app.js'])
</head>
<body>
<div class="announcement" aria-label="Оголошення">
    <div class="announcement__track" data-ticker-track>
        <span>Тут якась строчка яка біжить — може оголошення, може ще щось таке</span>
        <span>Тут якась строчка яка біжить — може оголошення, може ще щось таке</span>
    </div>
</div>

<header class="site-header" data-site-header>
    <nav class="navbar navbar-expand-xl">
        <div class="container-fluid site-header__inner">
            <a class="navbar-brand" href="{{ route('home') }}" aria-label="Рідна Віра — головна">
                <img src="{{ asset('assets/figma/home/logo.png') }}" alt="Духовний центр Рідна Віра">
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Відкрити меню">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav site-nav ms-auto align-items-xl-center">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ request()->is('pro-tsentr*') ? 'active' : '' }}" href="{{ url('/pro-tsentr') }}" role="button" data-bs-toggle="dropdown" aria-expanded="false">Про центр</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Управління</a></li>
                            <li><a class="dropdown-item" href="{{ url('/zviazok') }}">Зв’язок</a></li>
                            <li><a class="dropdown-item" href="#">Енциклопедія</a></li>
                            <li><a class="dropdown-item" href="{{ url('/pro-tsentr') }}">Про нас</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ request()->is('ridna-vira*') ? 'active' : '' }}" href="{{ url('/ridna-vira') }}" role="button" data-bs-toggle="dropdown" aria-expanded="false">Рідна Віра</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('home') }}#calendar">Календар</a></li>
                            <li><a class="dropdown-item" href="#">Книги</a></li>
                            <li><a class="dropdown-item" href="#">Святині</a></li>
                            <li><a class="dropdown-item" href="#">Боги</a></li>
                            <li><a class="dropdown-item" href="#">Обряди</a></li>
                            <li><a class="dropdown-item" href="#">Молитви</a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link {{ request()->is('novyny*') ? 'active' : '' }}" href="{{ url('/novyny') }}">Новини</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->is('statti*') ? 'active' : '' }}" href="{{ url('/statti') }}">Статті</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->is('tvorchist*') ? 'active' : '' }}" href="{{ url('/tvorchist') }}">Творчість</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->is('kramnychka*') ? 'active' : '' }}" href="{{ url('/kramnychka') }}">Крамниця</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->is('zviazok*') ? 'active' : '' }}" href="{{ url('/zviazok') }}">Зв’язок</a></li>
                </ul>

                <div class="header-actions">
                    <button class="language-switch" type="button" aria-label="Змінити мову">UA <span aria-hidden="true">⌄</span></button>
                    <a class="basket-link" href="{{ url('/koshyk') }}" aria-label="Кошик, 4 товари">
                        <img class="basket-link__image" src="{{ asset('assets/figma/home/basket.png') }}" alt="">
                        <span class="basket-link__badge">
                            <img src="{{ asset('assets/figma/home/star.svg') }}" alt="">
                            <b>4</b>
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
        <a href="{{ url('/pro-tsentr') }}">Про центр</a>
        <a href="{{ url('/ridna-vira') }}">Рідна Віра</a>
        <a href="{{ url('/novyny') }}">Новини</a>
        <a href="{{ url('/statti') }}">Статті</a>
        <a href="{{ url('/tvorchist') }}">Творчість</a>
        <a href="{{ url('/kramnychka') }}">Крамниця</a>
        <a href="{{ url('/zviazok') }}">Зв’язок</a>
    </nav>

    <div class="footer-partners" aria-label="Ресурси Рідної Віри">
        <a class="footer-partner footer-partner--polumya" href="https://yaro.dp.ua/" target="_blank" rel="noopener noreferrer" aria-label="Полум’я Роду">
            <img src="{{ asset('assets/figma/home/partner-polumya.png') }}" alt="Полум’я Роду">
        </a>

        <a class="footer-partner footer-partner--ridna" href="https://ridnovir.in.ua/" target="_blank" rel="noopener noreferrer" aria-label="Рідна Віра">
            <img src="{{ asset('assets/figma/home/footer-logo.png') }}" alt="Рідна Віра">
        </a>

        <a class="footer-partner footer-partner--svarga" href="https://svarga.com.ua/" target="_blank" rel="noopener noreferrer" aria-label="Сварга — портал Рідної Віри">
            <img src="{{ asset('assets/figma/home/partner-svarga.png') }}" alt="Сварга — портал Рідної Віри">
        </a>
    </div>

    <div class="footer-utilities">
        <div class="footer-payments" aria-label="Підтримувані способи оплати">
            <span class="payment-mark" title="Visa" role="img" aria-label="Visa">
                <svg viewBox="0 0 48 24" aria-hidden="true"><text x="3" y="17" fill="currentColor" font-family="Arial, sans-serif" font-size="16" font-style="italic" font-weight="700">VISA</text></svg>
            </span>
            <span class="payment-mark" title="Mastercard" role="img" aria-label="Mastercard">
                <svg viewBox="0 0 48 24" aria-hidden="true"><circle cx="19" cy="12" r="8" fill="#fff" opacity=".9"/><circle cx="29" cy="12" r="8" fill="#c28b2d" opacity=".88"/><path d="M24 6.5a8 8 0 0 1 0 11 8 8 0 0 1 0-11Z" fill="#e7c474"/></svg>
            </span>
            <span class="payment-mark" title="Apple Pay" role="img" aria-label="Apple Pay">
                <svg viewBox="0 0 48 24" aria-hidden="true"><text x="3" y="16" fill="currentColor" font-family="Arial, sans-serif" font-size="11" font-weight="700">Apple Pay</text></svg>
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
    <p class="footer-copy">© 2006–{{ date('Y') }} РІДНА ВІРА — Всі права захищені — Політика конфіденційності</p>
</footer>
</body>
</html>
