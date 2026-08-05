@extends('layouts.app')

@section('title', 'Зв’язок — Рідна Віра')
@section('description', 'Контакти Управи, крайових кіл і громад Духовного центру Рідна Віра.')

@section('content')
<section class="inner-hero inner-hero--about" aria-labelledby="contact-page-title">
    <div class="inner-hero__overlay"></div>
    <div class="inner-hero__content">
        <h1 id="contact-page-title">Зв’язок</h1>
        <nav class="inner-breadcrumbs" aria-label="Навігаційний шлях">
            <a href="{{ route('home') }}">Головна</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('about') }}">Про центр</a>
            <span aria-hidden="true">/</span>
            <span>Зв’язок</span>
        </nav>
    </div>
</section>

<section class="center-page center-page--contact">
    <div class="figma-container">
        <div class="center-intro center-intro--narrow">
            <p>Звертайтеся до проводу Духовного центру, керівників крайових кіл або найближчої громади. Допоможемо долучитися до спільноти, організувати обряд, лекцію, свято чи створити громаду у вашому місті.</p>
        </div>

        <section class="center-section" aria-labelledby="contact-main-title">
            <div class="center-section__heading">
                <span>Головні контакти</span>
                <h2 id="contact-main-title">Провід Духовного центру</h2>
            </div>

            <div class="contact-main-grid">
                <article class="contact-card contact-card--primary">
                    <span>Голова Рідної Віри</span>
                    <h3>Верховний волхв Світовит Пашник</h3>
                    <p>Запоріжжя</p>
                    <a href="tel:+380686439328">068 643 93 28</a>
                    <a href="tel:+380661537179">066 153 71 79</a>
                    <a href="mailto:pashnyk@ukr.net">pashnyk@ukr.net</a>
                    <a href="mailto:svitovyt@gmail.com">svitovyt@gmail.com</a>
                </article>

                <article class="contact-card contact-card--primary">
                    <span>Голова Управи та Січеславського Кола</span>
                    <h3>Волхв Яромир Мирошніченко</h3>
                    <p>Дніпро</p>
                    <a href="tel:+380934142016">093 414 20 16</a>
                    <a href="mailto:jaromirdp@gmail.com">jaromirdp@gmail.com</a>
                    <a href="https://svarga.com.ua/" target="_blank" rel="noopener noreferrer">svarga.com.ua</a>
                </article>

                <article class="contact-card">
                    <span>Запорозьке Коло</span>
                    <h3>Жрець Микола Кардач</h3>
                    <p>Запоріжжя</p>
                    <a href="tel:+380939946152">093 994 61 52</a>
                    <a href="mailto:panmykolazp@gmail.com">panmykolazp@gmail.com</a>
                </article>

                <article class="contact-card">
                    <span>Західне Коло</span>
                    <h3>Жриця Зореквіта Біленька</h3>
                    <p>Тернопіль</p>
                    <a href="tel:+380983870395">098 387 03 95</a>
                </article>
            </div>
        </section>

        <section class="center-section" aria-labelledby="communities-contact-title">
            <div class="center-section__heading">
                <span>Громади та представники</span>
                <h2 id="communities-contact-title">Знайдіть своє Коло</h2>
            </div>

            <div class="circle-contacts">
                <article class="circle-contact-card">
                    <div class="circle-contact-card__head">
                        <span>Запорозьке Коло</span>
                        <h3>Південь і Запоріжжя</h3>
                    </div>
                    <ul>
                        <li><b>Запоріжжя, «Права»</b><span>жрець Микола Кардач · 093 994 61 52</span></li>
                        <li><b>Запоріжжя, «Сварга»</b><span>обрядодій Ярослав Свидрань · 096 091 16 37</span></li>
                        <li><b>Запоріжжя, «Арійський шлях»</b><span>берегиня Ясна Яковенко · 063 153 26 81</span></li>
                        <li><b>Миколаїв, «Колограй»</b><span>Лютий Щербаков · 063 286 86 41</span></li>
                        <li><b>Київ</b><span>жрець Ярун Воєводін · 098 336 19 24</span></li>
                    </ul>
                </article>

                <article class="circle-contact-card">
                    <div class="circle-contact-card__head">
                        <span>Січеславське Коло</span>
                        <h3>Центр і Схід</h3>
                    </div>
                    <ul>
                        <li><b>Дніпро, «Полум’я Роду»</b><span>волхв Яромир Мирошніченко · 093 414 20 16</span></li>
                        <li><b>Павлоград</b><span>представник Серга Бондар · 095 583 41 10</span></li>
                        <li><b>Нікополь, «Матир-Сва»</b><span>староста Світлозара Ісаєва · 093 342 30 13</span></li>
                        <li><b>Безлюдівка, «Святославичі»</b><span>волхв Вірослав Зозуля · 095 474 46 68</span></li>
                        <li><b>Полтава</b><span>Велеслав Біленький · 096 624 41 10</span></li>
                    </ul>
                </article>

                <article class="circle-contact-card">
                    <div class="circle-contact-card__head">
                        <span>Західне Коло</span>
                        <h3>Захід України</h3>
                    </div>
                    <ul>
                        <li><b>Тернопіль, «Велесія»</b><span>жриця Зореквіта Біленька · 098 387 03 95</span></li>
                        <li><b>Хмельницький, «Коло Творення»</b><span>жриця Мілада Фастова · 097 233 19 00</span></li>
                        <li><b>Хмельницький</b><span>жрець Асур Яровий · 068 050 75 94</span></li>
                        <li><b>Широка Гребля, «Велес»</b><span>Олекса Покотило · 097 112 29 27</span></li>
                        <li><b>Вінниця</b><span>представник Богуслав Сулима · 067 587 57 10</span></li>
                    </ul>
                </article>
            </div>
        </section>

        <section class="contact-action-panel" aria-labelledby="contact-action-title">
            <div>
                <span>Співпраця</span>
                <h2 id="contact-action-title">Організувати захід або створити громаду</h2>
                <p>Духовні провідники можуть провести богославлення, календарне свято, обряд, лекцію, майстер-клас або зустріч у вашому місті.</p>
            </div>
            <a class="figma-button" href="mailto:jaromirdp@gmail.com?subject=Звернення%20з%20сайту%20Рідна%20Віра">Написати Управі</a>
        </section>

        <p class="contact-source-note">Розширений перелік громад і представників зберігається на старому порталі та буде поступово перенесений до нової бази сайту.</p>
    </div>
</section>
@endsection
