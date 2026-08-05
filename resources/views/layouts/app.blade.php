<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2a0902">
    <title>@yield('title', 'Рідна Віра — Духовний центр')</title>
    <meta name="description" content="@yield('description', 'Офіційний портал Духовного центру Рідна Віра')">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body>
<div class="announcement"><div class="announcement__track" data-ticker-track><span>Слава Роду! Слава Предкам!</span><span>Офіційний портал Духовного центру «Рідна Віра»</span><span>Новини громад, свята, обряди та духовна спадщина</span></div></div>
<header class="site-header" data-site-header>
    <nav class="navbar navbar-expand-xl py-2"><div class="container site-container">
        <a class="navbar-brand" href="{{ url('/') }}"><img src="{{ asset('assets/logo.svg') }}" alt="Духовний центр Рідна Віра"></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="mainNav"><ul class="navbar-nav ms-auto align-items-xl-center">
            <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="{{ url('/pro-tsentr') }}" data-bs-toggle="dropdown">Про центр</a><ul class="dropdown-menu"><li><a class="dropdown-item" href="#">Управління</a></li><li><a class="dropdown-item" href="{{ url('/zviazok') }}">Зв’язок</a></li><li><a class="dropdown-item" href="#">Енциклопедія</a></li><li><a class="dropdown-item" href="{{ url('/pro-tsentr') }}">Про нас</a></li></ul></li>
            <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="{{ url('/ridna-vira') }}" data-bs-toggle="dropdown">Рідна Віра</a><ul class="dropdown-menu"><li><a class="dropdown-item" href="#calendar">Календар</a></li><li><a class="dropdown-item" href="#">Книги</a></li><li><a class="dropdown-item" href="#">Святині</a></li><li><a class="dropdown-item" href="#">Боги</a></li><li><a class="dropdown-item" href="#">Обряди</a></li><li><a class="dropdown-item" href="#">Молитви</a></li></ul></li>
            <li class="nav-item"><a class="nav-link" href="{{ url('/novyny') }}">Новини</a></li><li class="nav-item"><a class="nav-link" href="{{ url('/statti') }}">Статті</a></li><li class="nav-item"><a class="nav-link" href="{{ url('/tvorchist') }}">Творчість</a></li><li class="nav-item"><a class="nav-link" href="{{ url('/kramnychka') }}">Крамничка</a></li><li class="nav-item"><a class="nav-link" href="{{ url('/zviazok') }}">Зв’язок</a></li><li class="nav-item"><a class="nav-link" href="#">UA</a></li>
            <li class="nav-item ms-xl-2"><a class="basket-link" href="{{ url('/koshyk') }}"><img src="{{ asset('assets/basket.svg') }}" alt="Кошик"><span>0</span></a></li>
        </ul></div>
    </div></nav>
</header>
<main>@yield('content')</main>
<footer class="site-footer"><div class="container site-container text-center">
    <img class="footer-ornament" src="{{ asset('assets/ornament.svg') }}" alt="">
    <nav class="footer-nav"><a href="{{ url('/pro-tsentr') }}">Про центр</a><a href="{{ url('/ridna-vira') }}">Рідна Віра</a><a href="{{ url('/novyny') }}">Новини</a><a href="{{ url('/statti') }}">Статті</a><a href="{{ url('/tvorchist') }}">Творчість</a><a href="{{ url('/kramnychka') }}">Крамничка</a><a href="{{ url('/zviazok') }}">Зв’язок</a></nav>
    <div class="footer-brands"><span>Полум’я Роду</span><strong>РІДНА ВІРА</strong><span>СВАРГА</span></div><div class="footer-rule"></div><p>© 2006–{{ date('Y') }} РІДНА ВІРА · Всі права захищені · Політика конфіденційності</p>
</div></footer>
</body></html>
