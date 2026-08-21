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
    const galleryTrack = aboutGallery.querySelector('[data-about-gallery-track]');
    const previousButton = aboutGallery.querySelector('[data-about-gallery-prev]');
    const nextButton = aboutGallery.querySelector('[data-about-gallery-next]');
    const anchorIndex = 1;
    let isAnimating = false;
    let pendingDirection = 0;
    let transitionFallback;

    const getSlides = () => Array.from(galleryTrack?.children ?? []);

    const renderGallery = (position = anchorIndex, animate = true) => {
        if (!galleryTrack) return;

        const slides = getSlides();
        if (slides.length === 0) return;

        if (!animate) {
            galleryTrack.classList.add('is-jumping');
        }

        const slideWidth = slides[0].getBoundingClientRect().width;
        const trackStyles = window.getComputedStyle(galleryTrack);
        const gap = Number.parseFloat(trackStyles.columnGap || trackStyles.gap) || 0;
        const shift = -(position * (slideWidth + gap) + slideWidth / 2);

        galleryTrack.style.setProperty('--about-gallery-shift', `${shift}px`);
        slides.forEach((slide, index) => {
            slide.classList.toggle('is-active', index === position);
        });

        if (!animate) {
            galleryTrack.getBoundingClientRect();
            requestAnimationFrame(() => {
                galleryTrack.classList.remove('is-jumping');
            });
        }
    };

    const finishMove = () => {
        if (!galleryTrack || !isAnimating || pendingDirection === 0) return;

        window.clearTimeout(transitionFallback);
        galleryTrack.classList.add('is-jumping');

        if (pendingDirection > 0) {
            const firstSlide = galleryTrack.firstElementChild;
            if (firstSlide) galleryTrack.append(firstSlide);
        } else {
            const lastSlide = galleryTrack.lastElementChild;
            if (lastSlide) galleryTrack.prepend(lastSlide);
        }

        pendingDirection = 0;
        renderGallery(anchorIndex, false);
        isAnimating = false;
    };

    const moveGallery = (direction) => {
        if (!galleryTrack || isAnimating || getSlides().length < 2) return;

        isAnimating = true;
        pendingDirection = direction;
        renderGallery(anchorIndex + direction, true);

        transitionFallback = window.setTimeout(finishMove, 550);
    };

    galleryTrack?.addEventListener('transitionend', (event) => {
        if (event.propertyName === 'transform') {
            finishMove();
        }
    });

    previousButton?.addEventListener('click', () => moveGallery(-1));
    nextButton?.addEventListener('click', () => moveGallery(1));

    renderGallery(anchorIndex, false);

    let galleryResizeTimer;
    window.addEventListener('resize', () => {
        window.clearTimeout(galleryResizeTimer);
        galleryResizeTimer = window.setTimeout(() => renderGallery(anchorIndex, false), 120);
    }, { passive: true });
}

const header = document.querySelector('[data-site-header]');
if (header) {
    window.addEventListener('scroll', () => header.classList.toggle('is-scrolled', window.scrollY > 24), { passive: true });
}
