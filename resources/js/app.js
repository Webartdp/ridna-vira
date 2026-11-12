import '../scss/fonts.scss';
import '../scss/corrections.scss';
import '../scss/refinements.scss';
import '../scss/about.scss';
import '../scss/center-pages.scss';
import '../scss/leadership-photos.scss';
import '../scss/documents.scss';
import '../scss/document-page.scss';
import '../scss/contact-map.scss';
import '../scss/contact-form.scss';
import '../scss/navigation.scss';
import '../scss/faith.scss';
import '../scss/faith-calendar.scss';
import '../scss/faith-books.scss';
import '../scss/holiday.scss';
import '../scss/reading-content.scss';
import './navigation.js';
import './contact-map.js';
import './contact-form.js';
import 'bootstrap';

const track = document.querySelector('[data-ticker-track]');

if (track) {
    const sourceItems = Array.from(track.children)
        .map((item) => item.textContent?.trim())
        .filter(Boolean);

    const buildTicker = () => {
        track.innerHTML = '';
        track.style.animation = 'none';

        const group = document.createElement('div');
        group.className = 'announcement__group';
        track.appendChild(group);

        const targetWidth = Math.max(window.innerWidth * 1.35, 1600);

        while (group.scrollWidth < targetWidth) {
            sourceItems.forEach((text) => {
                const item = document.createElement('span');
                item.textContent = text;
                group.appendChild(item);
            });
        }

        const clone = group.cloneNode(true);
        clone.setAttribute('aria-hidden', 'true');
        track.appendChild(clone);
        track.style.setProperty('--ticker-distance', `-${group.scrollWidth}px`);

        requestAnimationFrame(() => {
            track.style.animation = '';
        });
    };

    buildTicker();

    let resizeTimer;
    window.addEventListener('resize', () => {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(buildTicker, 180);
    }, { passive: true });
}

const languageSwitch = document.querySelector('.language-switch');
if (languageSwitch) {
    languageSwitch.textContent = 'UA';
}

const aboutGallery = document.querySelector('[data-about-gallery]');

if (aboutGallery) {
    const track = aboutGallery.querySelector('[data-about-gallery-track]');
    const slides = Array.from(track.children);
    const moveGallery = (direction) => {
        const width = track.clientWidth;
        const current = Math.round(track.scrollLeft / width);
        const next = (current + direction + slides.length) % slides.length;
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        track.scrollTo({ left: width * next, behavior: reduceMotion ? 'instant' : 'smooth' });
    };

    aboutGallery.querySelector('[data-about-gallery-prev]')?.addEventListener('click', () => moveGallery(-1));
    aboutGallery.querySelector('[data-about-gallery-next]')?.addEventListener('click', () => moveGallery(1));
}

const header = document.querySelector('[data-site-header]');
if (header) {
    window.addEventListener('scroll', () => header.classList.toggle('is-scrolled', window.scrollY > 24), { passive: true });
}
