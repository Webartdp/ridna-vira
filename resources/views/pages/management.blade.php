@extends('layouts.app')

@section('title', 'Управління — Рідна Віра')
@section('description', 'Провід, структура управління та основні напрями роботи Духовного центру Рідна Віра.')

@section('content')
<section class="inner-hero inner-hero--about" aria-labelledby="management-page-title">
    <div class="inner-hero__overlay"></div>
    <div class="inner-hero__content">
        <h1 id="management-page-title">Управління</h1>
        <nav class="inner-breadcrumbs" aria-label="Навігаційний шлях">
            <a href="{{ route('home') }}">Головна</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('about') }}">Про центр</a>
            <span aria-hidden="true">/</span>
            <span>Управління</span>
        </nav>
    </div>
</section>

<section class="center-page center-page--management">
    <div class="figma-container">
        <div class="center-intro">
            <p>Духовний центр «Рідна Віра» об’єднує громади та духовних провідників у спільне Коло. Управління Центром побудоване на відповідальності, взаємній підтримці, рішенні спільних справ і збереженні духовної традиції Русі-України.</p>
        </div>

        <section class="center-section" aria-labelledby="management-leadership-title">
            <div class="center-section__heading">
                <span>Провід</span>
                <h2 id="management-leadership-title">Керівники Духовного центру «Рідна Віра»</h2>
            </div>

            <div class="leadership-grid">
                <article class="leadership-card leadership-card--featured">
                    <div class="leadership-card__photo">
                        <img src="{{ asset('assets/people/svitovyt.webp') }}" alt="Волхв Світовит Пашник" loading="lazy">
                    </div>
                    <div class="leadership-card__content">
                        <span class="leadership-card__role">Голова ДЦ Рідної Віри, Волхв</span>
                        <h3>Світовит Пашник</h3>
                        <p>м. Запоріжжя</p>
                        <div class="leadership-card__contacts">
                            <a href="tel:+380686439328">068 643 93 28</a>
                            <a href="tel:+380661537179">066 153 71 79</a>
                            <a href="mailto:pashnyk@ukr.net">pashnyk@ukr.net</a>
                        </div>
                    </div>
                </article>

                <article class="leadership-card leadership-card--featured">
                    <div class="leadership-card__photo">
                        <img src="{{ asset('assets/people/yaromyr.webp') }}" alt="Волхв Яромир Мирошніченко" loading="lazy">
                    </div>
                    <div class="leadership-card__content">
                        <span class="leadership-card__role">Голова Управи, Волхв</span>
                        <h3>Яромир Мирошніченко</h3>
                        <p>м. Дніпро</p>
                        <div class="leadership-card__contacts">
                            <a href="tel:+380934142016">093 414 20 16</a>
                            <a href="mailto:jaromirdp@gmail.com">jaromirdp@gmail.com</a>
                        </div>
                    </div>
                </article>

                <article class="leadership-card leadership-card--featured">
                    <div class="leadership-card__photo">
                        <img src="{{ asset('assets/people/yasna.webp') }}" alt="Берегиня Ясна Яковенко" loading="lazy">
                    </div>
                    <div class="leadership-card__content">
                        <span class="leadership-card__role">Навчально-просвітницький відділ, Берегиня</span>
                        <h3>Ясна Яковенко</h3>
                        <p>м. Запоріжжя</p>
                        <div class="leadership-card__contacts">
                            <a href="tel:+380631532681">063 153 26 81</a>
                            <a href="mailto:yana.yakovenko@gmail.com">yana.yakovenko@gmail.com</a>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section class="center-section" aria-labelledby="management-structure-title">
            <div class="center-section__heading">
                <span>Устрій</span>
                <h2 id="management-structure-title">Як побудоване управління</h2>
            </div>

            <div class="structure-grid">
                <article class="structure-card">
                    <b>01</b>
                    <h3>Духовний центр</h3>
                    <p>Забезпечує єдність віровчення, розвиток громад, духовну освіту, обрядову діяльність і представництво конфесії.</p>
                </article>
                <article class="structure-card">
                    <b>02</b>
                    <h3>Управа</h3>
                    <p>Організовує поточну роботу Центру, координує відділи, інформаційні проєкти, навчання та взаємодію між громадами.</p>
                </article>
                <article class="structure-card">
                    <b>03</b>
                    <h3>Крайові Кола</h3>
                    <p>Об’єднують громади певного краю, допомагають проводити спільні свята, наради, навчання й організаційні заходи.</p>
                </article>
                <article class="structure-card">
                    <b>04</b>
                    <h3>Громади</h3>
                    <p>Місцеві релігійні об’єднання, у яких відбуваються богославлення, календарні свята, обряди та громадська праця.</p>
                </article>
            </div>
        </section>

        <section class="documents-panel" aria-labelledby="management-documents-title">
            <div>
                <span class="documents-panel__eyebrow">Документація</span>
                <h2 id="management-documents-title">Створення та реєстрація громади</h2>
                <p>На старому порталі зібрані зразки заяв, статуту, протоколів загальних зборів і документів для державної реєстрації релігійної громади.</p>
            </div>
            <a class="figma-button" href="https://www.svit.in.ua/upr/up3.htm" target="_blank" rel="noopener noreferrer">Переглянути документи</a>
        </section>

        <div class="center-cta">
            <p>Бажаєте створити громаду, приєднатися до Духовного центру або запропонувати співпрацю?</p>
            <a class="figma-button" href="{{ route('contact') }}">Зв’язатися з Управою</a>
        </div>
    </div>
</section>
@endsection
