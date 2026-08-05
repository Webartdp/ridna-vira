import '../scss/fonts.scss';
import '../scss/corrections.scss';
import '../scss/refinements.scss';
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

const header = document.querySelector('[data-site-header]');
if (header) {
    window.addEventListener('scroll', () => header.classList.toggle('is-scrolled', window.scrollY > 24), { passive: true });
}
