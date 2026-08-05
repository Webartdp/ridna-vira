<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1c0803">
    <title>@yield('title', 'Рідна Віра — Духовний центр')</title>
    <meta name="description" content="@yield('description', 'Офіційний портал Духовного центру Рідна Віра')">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
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

    <div class="footer-partners">
        <div class="partner-polumya" aria-label="Полум'я Роду">
            <span>Полум’я</span>
            <i>
                <img class="partner-polumya__circle" src="{{ asset('assets/figma/home/ellipse.svg') }}" alt="">
                <img class="partner-polumya__line partner-polumya__line--one" src="{{ asset('assets/figma/home/line2.svg') }}" alt="">
                <img class="partner-polumya__line partner-polumya__line--two" src="{{ asset('assets/figma/home/line3.svg') }}" alt="">
                <img class="partner-polumya__line partner-polumya__line--three" src="{{ asset('assets/figma/home/line4.svg') }}" alt="">
            </i>
            <span>Роду</span>
        </div>

        <img class="footer-main-logo" src="{{ asset('assets/figma/home/footer-logo.png') }}" alt="Рідна Віра">

        <div class="partner-svarga">
            <img src="{{ asset('assets/figma/home/logo-sva.svg') }}" alt="">
            <span><strong>Сварга</strong><small>Портал Рідної Віри</small></span>
        </div>
    </div>

    <div class="footer-rule"><img src="{{ asset('assets/figma/home/footer-line.svg') }}" alt=""></div>
    <p class="footer-copy">© 2006–{{ date('Y') }} РІДНА ВІРА — Всі права захищені — Політика конфіденційності</p>
</footer>
</body>
</html>
