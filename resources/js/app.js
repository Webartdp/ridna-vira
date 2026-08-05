import '../scss/fonts.scss';
import '../scss/corrections.scss';
import '../scss/refinements.scss';
import 'bootstrap';

const track = document.querySelector('[data-ticker-track]');
if (track) track.innerHTML += track.innerHTML;

const header = document.querySelector('[data-site-header]');
if (header) {
    window.addEventListener('scroll', () => header.classList.toggle('is-scrolled', window.scrollY > 24), { passive: true });
}
