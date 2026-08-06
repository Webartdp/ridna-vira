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

        <section class="document-library" aria-labelledby="management-documents-title">
            <div class="center-section__heading">
                <span>Документи та законодавство</span>
                <h2 id="management-documents-title">Інформаційна база Управи</h2>
            </div>
            <p class="document-library__intro">Статути, внутрішні положення, документи для громад і законодавчі матеріали зібрані в одному розділі та зберігаються безпосередньо на сервері Духовного центру.</p>

            <div class="document-groups">
                <article class="document-group">
                    <header class="document-group__head">
                        <span class="document-group__number">01</span>
                        <div>
                            <small>Документи центру</small>
                            <h3>Управління Духовного центру «Рідна Віра»</h3>
                        </div>
                    </header>
                    <div class="document-list">
                        <a class="document-item" href="{{ route('documents.download', 'statut-duhovnoho-tsentru') }}">
                            <span class="document-item__type">DOC</span>
                            <span class="document-item__body"><strong>Статут Духовного центру «Рідна Віра»</strong><small>Завантажити документ</small></span>
                            <span class="document-item__arrow" aria-hidden="true">↓</span>
                        </a>
                        <a class="document-item" href="{{ route('documents.download', 'vnutrishni-polozhennia') }}">
                            <span class="document-item__type">DOC</span>
                            <span class="document-item__body"><strong>Внутрішні Положення</strong><small>Завантажити документ</small></span>
                            <span class="document-item__arrow" aria-hidden="true">↓</span>
                        </a>
                        <a class="document-item" href="{{ route('documents.download', 'zaiava-pro-vstup-hromady') }}">
                            <span class="document-item__type">DOCX</span>
                            <span class="document-item__body"><strong>Заява про вступ релігійної громади до складу Духовного центру «Рідна Віра»</strong><small>Завантажити документ</small></span>
                            <span class="document-item__arrow" aria-hidden="true">↓</span>
                        </a>
                        <a class="document-item" href="{{ route('documents.download', 'reiestratsiia-statutu-hromady') }}">
                            <span class="document-item__type">DOC</span>
                            <span class="document-item__body"><strong>Документи для реєстрації статуту громади</strong><small>Завантажити документ</small></span>
                            <span class="document-item__arrow" aria-hidden="true">↓</span>
                        </a>
                        <a class="document-item" href="{{ route('documents.download', 'statut-akademii-ridnoi-viry') }}">
                            <span class="document-item__type">DOC</span>
                            <span class="document-item__body"><strong>Статут Академії Рідної Віри</strong><small>Завантажити документ</small></span>
                            <span class="document-item__arrow" aria-hidden="true">↓</span>
                        </a>
                    </div>
                </article>

                <article class="document-group">
                    <header class="document-group__head">
                        <span class="document-group__number">02</span>
                        <div>
                            <small>Правова база</small>
                            <h3>Законодавство України</h3>
                        </div>
                    </header>
                    <div class="document-list">
                        <a class="document-item" href="{{ route('documents.download', 'pro-svobodu-sovisti') }}">
                            <span class="document-item__type">DOCX</span>
                            <span class="document-item__body"><strong>Закон України «Про свободу совісті та релігійні організації»</strong><small>Редакція, знята з сайту ВР 30.08.2025</small></span>
                            <span class="document-item__arrow" aria-hidden="true">↓</span>
                        </a>
                        <a class="document-item" href="{{ route('documents.download', 'pro-pohovannia') }}">
                            <span class="document-item__type">DOCX</span>
                            <span class="document-item__body"><strong>Закон України «Про поховання та похоронну справу»</strong><small>Редакція, знята з сайту ВР 30.08.2025</small></span>
                            <span class="document-item__arrow" aria-hidden="true">↓</span>
                        </a>
                        <a class="document-item" href="{{ route('documents.download', 'pro-viiskove-kapelanstvo') }}">
                            <span class="document-item__type">DOCX</span>
                            <span class="document-item__body"><strong>Закон України «Про Службу військового капеланства»</strong><small>Редакція, знята з сайту ВР 06.09.2025</small></span>
                            <span class="document-item__arrow" aria-hidden="true">↓</span>
                        </a>
                        <a class="document-item" href="{{ route('documents.download', 'vypysky-iz-zakoniv') }}">
                            <span class="document-item__type">DOC</span>
                            <span class="document-item__body"><strong>Виписки із законів України</strong><small>Завантажити документ</small></span>
                            <span class="document-item__arrow" aria-hidden="true">↓</span>
                        </a>
                        <a class="document-item" href="{{ route('documents.download', 'komentar-kryminalnoho-kodeksu') }}">
                            <span class="document-item__type document-item__type--rar">RAR</span>
                            <span class="document-item__body"><strong>Науково-практичний коментар до Кримінального кодексу України</strong><small>Архів, 19 КБ</small></span>
                            <span class="document-item__arrow" aria-hidden="true">↓</span>
                        </a>
                    </div>
                </article>

                <article class="document-group document-group--wide">
                    <header class="document-group__head">
                        <span class="document-group__number">03</span>
                        <div>
                            <small>Офіційні ресурси</small>
                            <h3>Сайти з релігійного питання</h3>
                        </div>
                    </header>
                    <div class="document-list">
                        <a class="document-item" href="https://dess.gov.ua/" target="_blank" rel="noopener noreferrer">
                            <span class="document-item__type document-item__type--web">WEB</span>
                            <span class="document-item__body"><strong>Державна служба України з етнополітики та свободи совісті (ДЕСС)</strong><small>Перейти на офіційний державний сайт</small></span>
                            <span class="document-item__arrow" aria-hidden="true">↗</span>
                        </a>
                    </div>
                </article>
            </div>

            <p class="document-library__note">Усі файли зберігаються на сервері нового сайту. Публічні посилання на старий портал не використовуються.</p>
        </section>

        <div class="center-cta">
            <p>Бажаєте створити громаду, приєднатися до Духовного центру або запропонувати співпрацю?</p>
            <a class="figma-button" href="{{ route('contact') }}">Зв’язатися з Управою</a>
        </div>
    </div>
</section>
@endsection
