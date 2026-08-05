import '../scss/fonts.scss';
import '../scss/corrections.scss';
import '../scss/refinements.scss';
import '../scss/about.scss';
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
    const originalSlides = Array.from(galleryTrack?.children ?? []);

    if (galleryTrack && originalSlides.length > 0) {
        let activeOriginalIndex = originalSlides.findIndex((slide) => slide.classList.contains('is-active'));
        if (activeOriginalIndex < 0) activeOriginalIndex = 0;

        if (originalSlides.length > 1) {
            const firstClone = originalSlides[0].cloneNode(true);
            const lastClone = originalSlides.at(-1).cloneNode(true);

            firstClone.classList.remove('is-active');
            lastClone.classList.remove('is-active');
            firstClone.setAttribute('aria-hidden', 'true');
            lastClone.setAttribute('aria-hidden', 'true');

            galleryTrack.prepend(lastClone);
            galleryTrack.append(firstClone);
        }

        const slides = Array.from(galleryTrack.children);
        const hasClones = originalSlides.length > 1;
        let activeIndex = activeOriginalIndex + (hasClones ? 1 : 0);
        let isAnimating = false;

        const renderGallery = (animate = true) => {
            if (!animate) galleryTrack.classList.add('is-jumping');

            const slideWidth = slides[0].getBoundingClientRect().width;
            const trackStyles = window.getComputedStyle(galleryTrack);
            const gap = Number.parseFloat(trackStyles.columnGap || trackStyles.gap) || 0;
            const shift = -(activeIndex * (slideWidth + gap) + slideWidth / 2);

            galleryTrack.style.setProperty('--about-gallery-shift', `${shift}px`);
            slides.forEach((slide, index) => {
                slide.classList.toggle('is-active', index === activeIndex);
            });

            if (!animate) {
                galleryTrack.getBoundingClientRect();
                requestAnimationFrame(() => galleryTrack.classList.remove('is-jumping'));
            }
        };

        const moveGallery = (direction) => {
            if (isAnimating || slides.length < 2) return;

            isAnimating = true;
            activeIndex += direction;
            renderGallery(true);
        };

        galleryTrack.addEventListener('transitionend', (event) => {
            if (event.propertyName !== 'transform') return;

            if (hasClones && activeIndex === 0) {
                activeIndex = originalSlides.length;
                renderGallery(false);
            } else if (hasClones && activeIndex === originalSlides.length + 1) {
                activeIndex = 1;
                renderGallery(false);
            }

            isAnimating = false;
        });

        previousButton?.addEventListener('click', () => moveGallery(-1));
        nextButton?.addEventListener('click', () => moveGallery(1));

        renderGallery(false);

        let galleryResizeTimer;
        window.addEventListener('resize', () => {
            window.clearTimeout(galleryResizeTimer);
            galleryResizeTimer = window.setTimeout(() => renderGallery(false), 120);
        }, { passive: true });
    }
}

const header = document.querySelector('[data-site-header]');
if (header) {
    window.addEventListener('scroll', () => header.classList.toggle('is-scrolled', window.scrollY > 24), { passive: true });
}
