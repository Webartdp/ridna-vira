<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2a0902">
    <meta name="color-scheme" content="light">
    <title>@yield('title', 'Рідна Віра — Духовний центр')</title>
    <meta name="description" content="@yield('description', 'Офіційний портал Духовного центру Рідна Віра')">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>
<div class="announcement" aria-label="Оголошення">
    <div class="announcement__track" data-ticker-track>
        <span>Слава Роду! Слава Предкам!</span>
        <span>Офіційний портал Духовного центру «Рідна Віра»</span>
        <span>Новини громад, свята, обряди та духовна спадщина</span>
    </div>
</div>

<header class="site-header" data-site-header>
    <nav class="navbar navbar-expand-xl" aria-label="Головна навігація">
        <div class="container site-container">
            <a class="navbar-brand" href="{{ url('/') }}" aria-label="Рідна Віра — на головну">
                <img src="{{ asset('assets/logo.svg') }}" alt="Духовний центр Рідна Віра">
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Відкрити меню">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-xl-center">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ request()->is('pro-tsentr*') ? 'active' : '' }}" href="{{ url('/pro-tsentr') }}" role="button" data-bs-toggle="dropdown" aria-expanded="false">Про центр</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ url('/pro-tsentr') }}">Про нас</a></li>
                            <li><a class="dropdown-item" href="#">Управління</a></li>
                            <li><a class="dropdown-item" href="#">Енциклопедія</a></li>
                            <li><a class="dropdown-item" href="{{ url('/zviazok') }}">Зв’язок</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ request()->is('ridna-vira*') ? 'active' : '' }}" href="{{ url('/ridna-vira') }}" role="button" data-bs-toggle="dropdown" aria-expanded="false">Рідна Віра</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ url('/ridna-vira') }}#calendar">Календар</a></li>
                            <li><a class="dropdown-item" href="#">Книги</a></li>
                            <li><a class="dropdown-item" href="#">Святині</a></li>
                            <li><a class="dropdown-item" href="#">Рідні Боги</a></li>
                            <li><a class="dropdown-item" href="#">Обряди</a></li>
                            <li><a class="dropdown-item" href="#">Слави</a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link {{ request()->is('novyny*') ? 'active' : '' }}" href="{{ url('/novyny') }}">Новини</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->is('statti*') ? 'active' : '' }}" href="{{ url('/statti') }}">Статті</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->is('tvorchist*') ? 'active' : '' }}" href="{{ url('/tvorchist') }}">Творчість</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->is('kramnychka*') ? 'active' : '' }}" href="{{ url('/kramnychka') }}">Крамничка</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->is('zviazok*') ? 'active' : '' }}" href="{{ url('/zviazok') }}">Зв’язок</a></li>
                    <li class="nav-item"><a class="nav-link nav-language" href="#" aria-label="Українська мова">UA</a></li>
                    <li class="nav-item ms-xl-2">
                        <a class="basket-link" href="{{ url('/koshyk') }}" aria-label="Кошик, товарів: 0">
                            <img src="{{ asset('assets/basket.svg') }}" alt="">
                            <span>0</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<main>@yield('content')</main>

<footer class="site-footer">
    <div class="container site-container text-center">
        <img class="footer-ornament" src="{{ asset('assets/ornament.svg') }}" alt="">

        <nav class="footer-nav" aria-label="Навігація у підвалі">
            <a href="{{ url('/pro-tsentr') }}">Про центр</a>
            <a href="{{ url('/ridna-vira') }}">Рідна Віра</a>
            <a href="{{ url('/novyny') }}">Новини</a>
            <a href="{{ url('/statti') }}">Статті</a>
            <a href="{{ url('/tvorchist') }}">Творчість</a>
            <a href="{{ url('/kramnychka') }}">Крамничка</a>
            <a href="{{ url('/zviazok') }}">Зв’язок</a>
        </nav>

        <div class="footer-brands" aria-label="Організації та проєкти">
            <span>Полум’я Роду</span>
            <strong>Рідна Віра</strong>
            <span>Сварга</span>
        </div>

        <div class="footer-rule"></div>
        <p>© 2006–{{ date('Y') }} РІДНА ВІРА · Всі права захищені · Політика конфіденційності</p>
    </div>
</footer>
</body>
</html>
