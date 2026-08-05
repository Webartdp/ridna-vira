#!/usr/bin/env bash
set -euo pipefail

mkdir -p resources/views/layouts resources/views/pages resources/scss resources/js public/assets

cat > package.json <<'JSON'
{
  "private": true,
  "type": "module",
  "scripts": {"build": "vite build", "dev": "vite"},
  "devDependencies": {
    "@popperjs/core": "^2.11.8",
    "bootstrap": "^5.3.8",
    "laravel-vite-plugin": "^2.0.1",
    "sass": "^1.89.2",
    "vite": "^7.0.4"
  }
}
JSON

cat > vite.config.js <<'JS'
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [laravel({
        input: ['resources/scss/app.scss', 'resources/js/app.js'],
        refresh: true,
    })],
});
JS

cat > routes/web.php <<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

foreach ([
    'pro-tsentr' => 'Про центр',
    'ridna-vira' => 'Рідна Віра',
    'novyny' => 'Новини',
    'statti' => 'Статті',
    'tvorchist' => 'Творчість',
    'kramnychka' => 'Крамничка',
    'zviazok' => 'Зв’язок',
    'koshyk' => 'Кошик',
] as $slug => $title) {
    Route::view('/'.$slug, 'pages.placeholder', compact('title'));
}
PHP

cat > resources/js/app.js <<'JS'
import 'bootstrap';

const track = document.querySelector('[data-ticker-track]');
if (track) track.innerHTML += track.innerHTML;

const header = document.querySelector('[data-site-header]');
if (header) {
    window.addEventListener('scroll', () => header.classList.toggle('is-scrolled', window.scrollY > 24), { passive: true });
}
JS

cat > resources/views/layouts/app.blade.php <<'BLADE'
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
BLADE

cat > resources/views/home.blade.php <<'BLADE'
@extends('layouts.app')
@section('title', 'Рідна Віра — Духовний центр')
@section('content')
<section class="hero"><div class="hero__shade"></div><div class="container site-container hero__content"><p>Духовний центр</p><h1>РІДНА ВІРА</h1><img src="{{ asset('assets/ornament.svg') }}" alt=""></div></section>
<section class="primary-links"><div class="container site-container"><div class="row g-3">
@foreach ([['Святині','sanctuary'],['Рідні Боги','gods'],['Обряди','rites'],['Слави','glory']] as [$title,$icon])
<div class="col-6 col-lg-3"><a class="primary-card" href="#"><svg><use href="{{ asset('assets/icons.svg') }}#{{ $icon }}"/></svg><strong>{{ $title }}</strong></a></div>
@endforeach
</div></div></section>
<section class="communities"><div class="communities__image"></div><div class="container site-container communities__inner"><div class="paper-card text-center"><h2>Громади та представництва</h2><div class="row g-4 text-start"><div class="col-md-6"><p>Духовний центр об’єднує громади Рідної Віри, духовних провідників та однодумців, які зберігають звичаї, обряди й світогляд наших Предків.</p></div><div class="col-md-6"><p>На порталі буде представлена карта громад, контакти представництв, календар подій та матеріали для тих, хто прагне долучитися до живої традиції.</p></div></div><div class="row g-3 mt-1">@foreach (['Полум’я Роду','Права','Росичі'] as $name)<div class="col-md-4"><a class="community-tile" href="#"><span>{{ $name }}</span></a></div>@endforeach</div><a class="btn btn-gold mt-4" href="#">Всі громади</a></div></div></section>
<section class="svarog" id="calendar"><div class="container site-container text-center"><h2>Коло Свароже</h2><div class="svarog__layout"><div class="svarog__side">@foreach ([['Велес','veles'],['Мокоша','mokosh'],['Коляда','kolyada'],['Перун','perun']] as [$name,$icon])<a class="deity-card" href="#"><svg><use href="{{ asset('assets/icons.svg') }}#{{ $icon }}"/></svg><strong>{{ $name }}</strong></a>@endforeach</div><img class="svarog__wheel" src="{{ asset('assets/wheel.svg') }}" alt="Коло Свароже"><div class="svarog__side">@foreach ([['Білобог','bilobog'],['Дажбог','dazhbog'],['Купайло','kupala'],['Всі свята','feasts']] as [$name,$icon])<a class="deity-card" href="#"><svg><use href="{{ asset('assets/icons.svg') }}#{{ $icon }}"/></svg><strong>{{ $name }}</strong></a>@endforeach</div></div><a class="btn btn-gold mt-3" href="#">Всі свята</a></div></section>
@foreach (['Новини','Статті','Творчість'] as $section)
<section class="content-section"><div class="container site-container"><h2>{{ $section }}</h2><div class="row g-4"><div class="col-lg-7"><a class="featured-story" href="#"><img src="{{ asset('assets/article.svg') }}" alt="Світолад української оселі"><div class="featured-story__shade"></div><div class="featured-story__content"><span class="date-pill">20.03.2027</span><h3>Світолад української оселі</h3><p>Духовна спадщина, звичаї та символіка українського дому.</p><b>→</b></div></a></div><div class="col-lg-5"><div class="story-list">@for ($i=0;$i<3;$i++)<a class="story-item" href="#"><img src="{{ asset('assets/article.svg') }}" alt=""><span><strong>Структура замовляння у слов’ян</strong><small>20.03.2027</small></span></a>@endfor</div></div></div></div></section>
@endforeach
@endsection
BLADE

cat > resources/views/pages/placeholder.blade.php <<'BLADE'
@extends('layouts.app')
@section('title', $title.' — Рідна Віра')
@section('content')
<section class="page-hero"><div class="container site-container"><h1>{{ $title }}</h1><p><a href="{{ url('/') }}">Головна</a> / {{ $title }}</p></div></section>
<section class="placeholder"><div class="container site-container"><div class="paper-card text-center"><h2>{{ $title }}</h2><p>Сторінка вже підключена до структури Laravel. Верстку за відповідним PDF-макетом буде додано наступним етапом.</p></div></div></section>
@endsection
BLADE

cat > resources/scss/app.scss <<'SCSS'
@import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Montserrat:wght@400;500;600;700&display=swap');
@import 'bootstrap/scss/bootstrap';
$gold:#c89125;$gold2:#e0b552;$brown:#2a0902;$ink:#24140e;$paper:#f2ede1;
:root{--gold:#{$gold};--brown:#{$brown}}
body{margin:0;color:$ink;font-family:Montserrat,sans-serif;background-color:$paper;background-image:radial-gradient(circle at 20% 30%,rgba(255,255,255,.7) 0 1px,transparent 1.5px),repeating-linear-gradient(0deg,rgba(70,40,20,.025) 0 1px,transparent 1px 4px);background-size:9px 9px,100% 4px}h1,h2,h3,.primary-card strong,.deity-card strong{font-family:'Cormorant Garamond',serif;font-weight:700}.site-container{max-width:1160px}a{color:inherit;text-decoration:none}.announcement{height:25px;overflow:hidden;background:$gold;color:#fff;font-size:.68rem;text-transform:uppercase;white-space:nowrap}.announcement__track{display:inline-flex;align-items:center;gap:4rem;min-width:max-content;height:100%;padding-left:100%;animation:ticker 30s linear infinite}@keyframes ticker{to{transform:translateX(-50%)}}
.site-header{position:sticky;top:0;z-index:1000;background:rgba(250,247,239,.97);border-bottom:1px solid rgba(60,30,10,.08);transition:.25s}.site-header.is-scrolled{box-shadow:0 8px 25px rgba(45,15,4,.15)}.navbar-brand img{width:250px}.nav-link{padding:.8rem .58rem!important;color:$ink;font-size:.8rem;font-weight:600;text-transform:uppercase}.nav-link:hover,.dropdown-item:hover{color:$gold}.dropdown-menu{border:0;border-radius:0 0 14px 14px;padding:.65rem;box-shadow:0 12px 28px rgba(45,15,4,.2)}.dropdown-item{border-radius:7px;text-transform:uppercase;font-size:.82rem}.basket-link{position:relative;display:block;width:50px}.basket-link img{width:50px}.basket-link span{position:absolute;right:-2px;top:0;width:18px;height:18px;border-radius:50%;display:grid;place-items:center;background:$brown;color:#fff;font-size:.65rem}
.hero{position:relative;min-height:560px;display:grid;place-items:center;text-align:center;color:#fff;background:url('/assets/hero.svg') center/cover no-repeat}.hero__shade{position:absolute;inset:0;background:linear-gradient(rgba(8,5,2,.15),rgba(8,5,2,.48))}.hero__content{position:relative;z-index:1;transform:translateY(-25px)}.hero p{margin:0 0 -.25rem;font-family:'Cormorant Garamond',serif;font-size:1.5rem;text-transform:uppercase}.hero h1{font-size:clamp(4rem,9vw,7.6rem);line-height:.9;text-shadow:0 5px 22px #0008}.hero img{width:min(430px,70vw);height:90px;object-fit:contain}
.primary-links{position:relative;z-index:4;margin-top:-90px;padding-bottom:35px}.primary-card,.deity-card,.paper-card{background:rgba(255,253,247,.96);border:2px solid #fff;box-shadow:0 9px 16px rgba(46,22,10,.24)}.primary-card{min-height:205px;border-radius:15px;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1rem;transition:.25s}.primary-card:hover{transform:translateY(-7px)}.primary-card svg{width:125px;height:125px}.primary-card strong{font-size:1.55rem;text-transform:uppercase}
.communities{position:relative;padding:120px 0 70px;margin-top:-35px}.communities__image{position:absolute;inset:0 0 auto;height:510px;background:url('/assets/communities.svg') center/cover no-repeat}.communities__inner{position:relative;z-index:1;padding-top:285px}.paper-card{border-radius:16px;padding:clamp(1.5rem,4vw,3rem)}.paper-card h2,.svarog h2,.content-section h2{font-size:clamp(2rem,4vw,3.1rem);text-align:center;text-transform:uppercase;margin-bottom:1.5rem}.paper-card p{line-height:1.7}.community-tile{min-height:100px;border:3px solid #fff;border-radius:11px;background:linear-gradient(#0002,#0008),url('/assets/communities.svg') center/cover;display:grid;place-items:end center;padding:.65rem;color:#fff;box-shadow:0 5px 12px #3214;text-transform:uppercase;font-family:'Cormorant Garamond',serif;font-weight:700}.btn-gold{min-width:175px;padding:.75rem 1.4rem;border:0;border-radius:7px;color:#fff;background:linear-gradient($gold2,$gold);box-shadow:0 5px 10px #6433}.btn-gold:hover{color:#fff;background:$brown}
.svarog{padding:55px 0 80px}.svarog__layout{display:grid;grid-template-columns:minmax(180px,1fr) minmax(340px,590px) minmax(180px,1fr);align-items:center;gap:1rem}.svarog__wheel{width:100%;max-width:590px;filter:drop-shadow(0 12px 16px #5023)}.svarog__side{display:flex;flex-direction:column;gap:1rem}.deity-card{min-height:110px;border-radius:13px;display:flex;align-items:center;gap:.4rem;padding:.4rem .7rem;transition:.2s}.deity-card:hover{transform:translateY(-4px)}.deity-card svg{width:82px;height:82px;flex:0 0 82px}.deity-card strong{font-size:1.1rem;text-transform:uppercase}
.content-section{padding:45px 0}.featured-story{position:relative;display:block;height:360px;overflow:hidden;border:3px solid #fff;border-radius:14px;box-shadow:0 8px 16px #3215;color:#fff}.featured-story>img{width:100%;height:100%;object-fit:cover;transition:.4s}.featured-story:hover>img{transform:scale(1.04)}.featured-story__shade{position:absolute;inset:0;background:linear-gradient(transparent 30%,rgba(25,8,2,.84))}.featured-story__content{position:absolute;inset:0;display:flex;flex-direction:column;justify-content:end;padding:1.6rem}.date-pill,.story-item small{align-self:flex-start;padding:.28rem .7rem;border-radius:20px;background:$gold;color:#fff;font-size:.68rem}.featured-story h3{margin:.7rem 0 .2rem;font-size:2rem;text-transform:uppercase}.featured-story p{margin:0}.featured-story b{position:absolute;right:1.5rem;bottom:1.1rem;font-size:2rem}.story-list{display:grid;gap:1rem;height:100%}.story-item{display:grid;grid-template-columns:150px 1fr;gap:1rem;align-items:center;padding:.55rem;border-radius:12px;background:#fff9;transition:.2s}.story-item:hover{transform:translateX(5px);background:#fff}.story-item img{width:150px;height:90px;object-fit:cover;border:3px solid #fff;border-radius:11px;box-shadow:0 4px 10px #3214}.story-item span{display:flex;flex-direction:column;gap:.6rem}.story-item strong{font-family:'Cormorant Garamond',serif;font-size:1.25rem;line-height:1.05;text-transform:uppercase}.story-item small{width:max-content}
.page-hero{padding:75px 0;text-align:center;color:#fff;background:linear-gradient(#0006,#0008),url('/assets/hero.svg') center/cover}.page-hero h1{font-size:clamp(2.8rem,7vw,5rem);text-transform:uppercase}.placeholder{min-height:450px;padding:70px 0}.site-footer{margin-top:60px;padding:35px 0 22px;background:$brown;color:#d5a84c}.footer-ornament{width:min(450px,75vw);height:100px}.footer-nav{display:flex;flex-wrap:wrap;justify-content:center;gap:1rem 1.8rem;margin:.5rem 0 1.5rem;font-size:.78rem;text-transform:uppercase}.footer-brands{display:flex;justify-content:center;align-items:center;gap:clamp(1.5rem,7vw,5rem);color:#fff;font-family:'Cormorant Garamond',serif;font-size:1.3rem;text-transform:uppercase}.footer-brands strong{font-size:1.6rem}.footer-rule{height:2px;max-width:760px;margin:1.2rem auto;background:#fffc}.site-footer p{margin:0;color:#fff5;font-size:.67rem}
@media(max-width:1199.98px){.navbar-brand img{width:215px}.navbar-collapse{padding:1rem 0}}@media(max-width:991.98px){.hero{min-height:500px}.primary-links{margin-top:-65px}.svarog__layout{grid-template-columns:1fr 1fr}.svarog__wheel{grid-column:1/-1;grid-row:1;max-width:520px}.svarog__side{grid-row:2}}@media(max-width:767.98px){.announcement{display:none}.navbar-brand img{width:185px}.hero{min-height:430px}.hero h1{font-size:4rem}.primary-links{margin-top:-45px}.primary-card{min-height:165px}.primary-card svg{width:90px;height:90px}.primary-card strong{font-size:1.12rem;text-align:center}.communities__image{height:360px}.communities__inner{padding-top:205px}.svarog__layout{display:flex;flex-direction:column}.svarog__side{display:grid;grid-template-columns:1fr 1fr;width:100%}.deity-card{min-height:95px;flex-direction:column;justify-content:center}.deity-card svg{width:62px;height:62px}.story-item{grid-template-columns:115px 1fr}.story-item img{width:115px}.footer-brands{flex-direction:column;gap:.6rem}}@media(max-width:480px){.hero h1{font-size:3.2rem}.primary-card{min-height:145px}.paper-card{padding:1.2rem}.svarog__side{grid-template-columns:1fr}.featured-story{height:300px}}
SCSS

cat > public/assets/logo.svg <<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 620 170"><defs><radialGradient id="g"><stop stop-color="#ffe381"/><stop offset=".5" stop-color="#d39827"/><stop offset="1" stop-color="#7e3e0d"/></radialGradient></defs><g fill="none" stroke="url(#g)" stroke-width="5" transform="translate(80 85)"><circle r="48"/><circle r="31"/><path d="M0-76V-51M54-54 36-36M76 0H51M54 54 36 36M0 76V51M-54 54-36 36M-76 0H-51M-54-54-36-36"/><path d="M-22 13Q0-15 22 13Q0 43-22 13Z"/><circle cy="8" r="8" fill="#d39827"/></g><text x="160" y="62" fill="#5b2411" font-family="Georgia,serif" font-size="25" font-weight="700">ДУХОВНИЙ ЦЕНТР</text><text x="155" y="120" fill="#5b2411" font-family="Georgia,serif" font-size="58" font-weight="700">РІДНА ВІРА</text></svg>
SVG
cat > public/assets/basket.svg <<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><defs><linearGradient id="a" x2="0" y2="1"><stop stop-color="#d56520"/><stop offset="1" stop-color="#7f270c"/></linearGradient></defs><path fill="url(#a)" stroke="#5b1807" stroke-width="5" d="M18 48h84l-9 48H27z"/><path fill="none" stroke="#61200c" stroke-width="8" stroke-linecap="round" d="M31 49 51 23M89 49 69 23"/><path fill="none" stroke="#ffd16a" stroke-width="4" d="M29 62h62M26 76h68M43 50v44M60 50v44M77 50v44"/></svg>
SVG
cat > public/assets/ornament.svg <<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 180"><g fill="none" stroke="#c9952c" stroke-width="9"><path d="M30 90h80l45-45 45 45 45-45 45 45 45-45 45 45 45-45 45 45 45-45 45 45 45-45 45 45 45-45 45 45h80"/><path d="M110 90l45 45 45-45 45 45 45-45 45 45 45-45 45 45 45-45 45 45 45-45 45 45 45-45 45 45"/><circle cx="500" cy="90" r="42"/><path d="M500 48v84M458 90h84M470 60l60 60M530 60l-60 60"/></g></svg>
SVG
cat > public/assets/hero.svg <<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 900"><defs><linearGradient id="sky" x2="0" y2="1"><stop stop-color="#849b79"/><stop offset="1" stop-color="#233522"/></linearGradient><linearGradient id="wood" x2="1"><stop stop-color="#6e492b"/><stop offset=".5" stop-color="#2a1c13"/><stop offset="1" stop-color="#6b472b"/></linearGradient></defs><rect width="1600" height="900" fill="url(#sky)"/><g fill="#172b1b" opacity=".72"><path d="M0 520 90 100l75 420 80-500 85 500 95-390 70 390 120-480 80 480 100-430 80 430 110-500 75 500 115-390 70 390 100-470 95 470 80-350 85 350v380H0Z"/></g><path stroke="url(#wood)" stroke-width="38" stroke-linecap="round" d="M0 660 360 360M1600 660 1240 360"/><g fill="url(#wood)" stroke="#29170e" stroke-width="8"><path d="M650 720V160l70-80 70 80v560z"/><path d="M1010 720V240l55-65 55 65v480z"/><path d="M350 720V300l45-55 45 55v420z"/></g><g fill="none" stroke="#c79a55" stroke-width="13" opacity=".8"><circle cx="720" cy="310" r="65"/><path d="M720 245v130M655 310h130M675 265l90 90M765 265l-90 90"/><circle cx="1065" cy="380" r="48"/><path d="M1065 332v96M1017 380h96"/><circle cx="395" cy="425" r="42"/></g><rect y="760" width="1600" height="140" fill="#101b11" opacity=".65"/></svg>
SVG
cat > public/assets/communities.svg <<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 900"><defs><linearGradient id="g" x2="0" y2="1"><stop stop-color="#7f9b77"/><stop offset="1" stop-color="#293a25"/></linearGradient></defs><rect width="1600" height="900" fill="url(#g)"/><g fill="#314d2e" opacity=".8"><circle cx="120" cy="220" r="180"/><circle cx="390" cy="170" r="210"/><circle cx="720" cy="220" r="230"/><circle cx="1080" cy="180" r="220"/><circle cx="1450" cy="220" r="240"/></g><rect y="590" width="1600" height="310" fill="#3e5d31"/><g transform="translate(250 315)"><path stroke="#c7aa62" stroke-width="14" d="M550 0v390M0 300h1100M80 300 550 0 1020 300"/><g fill="#dfc6a3" stroke="#462116" stroke-width="7"><circle cx="180" cy="260" r="45"/><path d="M120 500 140 315h80l30 185z"/><circle cx="350" cy="250" r="45"/><path d="M290 500 310 305h80l30 195z"/><circle cx="550" cy="235" r="48"/><path d="M485 500 505 295h90l30 205z"/><circle cx="750" cy="250" r="45"/><path d="M690 500 710 305h80l30 195z"/><circle cx="920" cy="260" r="45"/><path d="M860 500 880 315h80l30 185z"/></g></g></svg>
SVG
cat > public/assets/article.svg <<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 650"><rect width="1000" height="650" fill="#9fc48c"/><circle cx="180" cy="115" r="170" fill="#5e8b56"/><circle cx="820" cy="100" r="190" fill="#4f7b48"/><rect y="420" width="1000" height="230" fill="#6f9b54"/><g transform="translate(190 145)"><rect x="80" y="170" width="520" height="270" fill="#d7b17b" stroke="#5d331c" stroke-width="10"/><path d="M20 190 340 10l330 180z" fill="#7b4b27" stroke="#4b2817" stroke-width="12"/><rect x="155" y="245" width="105" height="100" fill="#79a8b5" stroke="#fff" stroke-width="12"/><rect x="420" y="245" width="105" height="100" fill="#79a8b5" stroke="#fff" stroke-width="12"/><rect x="300" y="275" width="90" height="165" fill="#6b3d20"/></g><g fill="#bf1717"><circle cx="80" cy="560" r="24"/><circle cx="145" cy="520" r="20"/><circle cx="210" cy="575" r="26"/><circle cx="785" cy="555" r="24"/><circle cx="860" cy="520" r="22"/><circle cx="930" cy="570" r="27"/></g></svg>
SVG
cat > public/assets/wheel.svg <<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 900"><defs><radialGradient id="g"><stop stop-color="#f3c758"/><stop offset=".55" stop-color="#b66e13"/><stop offset="1" stop-color="#4d2107"/></radialGradient></defs><circle cx="450" cy="450" r="420" fill="url(#g)" stroke="#5c2708" stroke-width="20"/><circle cx="450" cy="450" r="320" fill="#2f1205" stroke="#f0bd45" stroke-width="14"/><circle cx="450" cy="450" r="155" fill="url(#g)" stroke="#f6d56f" stroke-width="12"/><g stroke="#f4c65b" stroke-width="12" fill="none">@for($i=0;$i<12;$i++)<path d="M450 130V770" transform="rotate({{ $i*15 }} 450 450)"/>@endfor</g><circle cx="450" cy="450" r="75" fill="#341305" stroke="#ffd76c" stroke-width="12"/><path d="M450 390v120M390 450h120M408 408l84 84M492 408l-84 84" stroke="#ffd76c" stroke-width="16"/></svg>
SVG
# Replace Blade syntax accidentally unsuitable inside SVG with static rays.
python3 - <<'PY'
p='public/assets/wheel.svg'
s=open(p).read()
r=''.join(f'<path d="M450 130V770" transform="rotate({i*15} 450 450)"/>' for i in range(12))
s=s.replace('@for($i=0;$i<12;$i++)<path d="M450 130V770" transform="rotate({{ $i*15 }} 450 450)"/>@endfor',r)
open(p,'w').write(s)
PY
cat > public/assets/icons.svg <<'SVG'
<svg xmlns="http://www.w3.org/2000/svg"><defs>
<linearGradient id="gold" x2="0" y2="1"><stop stop-color="#ffe486"/><stop offset=".5" stop-color="#ca8621"/><stop offset="1" stop-color="#69300b"/></linearGradient>
<symbol id="sanctuary" viewBox="0 0 120 120"><circle cx="60" cy="60" r="54" fill="url(#gold)"/><path d="M28 86h64M35 82V48h50v34M28 48l32-25 32 25M50 82V60h20v22" fill="none" stroke="#57200a" stroke-width="6"/></symbol>
<symbol id="gods" viewBox="0 0 120 120"><circle cx="60" cy="60" r="54" fill="url(#gold)"/><circle cx="60" cy="38" r="13" fill="#57200a"/><circle cx="35" cy="55" r="10" fill="#57200a"/><circle cx="85" cy="55" r="10" fill="#57200a"/><path d="M24 93q11-35 31-20 5-26 10 0 20-15 31 20" fill="#57200a"/></symbol>
<symbol id="rites" viewBox="0 0 120 120"><circle cx="60" cy="60" r="54" fill="url(#gold)"/><path d="M34 83h52l-8 19H42zM43 80q17-20 34 0M60 72q-24-20 0-48 24 28 0 48z" fill="#57200a"/></symbol>
<symbol id="glory" viewBox="0 0 120 120"><circle cx="60" cy="60" r="54" fill="url(#gold)"/><path d="M60 20v76M25 61h70M34 35l52 52M86 35 34 87" stroke="#57200a" stroke-width="8"/><circle cx="60" cy="60" r="17" fill="#f7c84d" stroke="#57200a" stroke-width="5"/></symbol>
<symbol id="veles" viewBox="0 0 120 120"><use href="#gods"/></symbol><symbol id="mokosh" viewBox="0 0 120 120"><use href="#glory"/></symbol><symbol id="kolyada" viewBox="0 0 120 120"><use href="#sanctuary"/></symbol><symbol id="bilobog" viewBox="0 0 120 120"><use href="#glory"/></symbol><symbol id="dazhbog" viewBox="0 0 120 120"><use href="#rites"/></symbol><symbol id="perun" viewBox="0 0 120 120"><use href="#gods"/></symbol><symbol id="kupala" viewBox="0 0 120 120"><use href="#rites"/></symbol><symbol id="feasts" viewBox="0 0 120 120"><use href="#sanctuary"/></symbol>
</defs></svg>
SVG

cat > .env.example <<'ENV'
APP_NAME="Рідна Віра"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost
APP_LOCALE=uk
APP_FALLBACK_LOCALE=uk
APP_FAKER_LOCALE=uk_UA
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ridna_vira
DB_USERNAME=root
DB_PASSWORD=
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@ridnavira.com.ua"
MAIL_FROM_NAME="${APP_NAME}"
ENV

rm -f resources/views/welcome.blade.php
cat > README.md <<'MD'
# Рідна Віра

Офіційний сайт Духовного центру «Рідна Віра» для домену `ridnavira.com.ua`.

## Стек

- Laravel 13
- PHP 8.3+
- Blade
- Bootstrap 5
- SCSS
- Vite
- MySQL / MariaDB

## Запуск

```bash
cp .env.example .env
composer install
php artisan key:generate
npm install
npm run build
php artisan serve
```

## Перший етап

Створено Laravel-каркас, адаптивну шапку, рухомий рядок, головну сторінку, блок громад, Коло Свароже, блоки новин/статей/творчості, футер і маршрути основних сторінок.
MD

php artisan route:list >/dev/null
