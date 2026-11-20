import Swiper from 'swiper';
import { A11y, Navigation, Thumbs } from 'swiper/modules';
import 'swiper/css';

const gallery = document.querySelector('[data-product-gallery]');
const rail = gallery.querySelector('.product-gallery__thumbnails');
const links = Array.from(rail.querySelectorAll('a'));
const stage = gallery.querySelector('.product-gallery__stage');
const zoomLinks = Array.from(stage.querySelectorAll('[data-fancybox]'));
const speed = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 250;

const thumbnails = new Swiper(rail, {
    slidesPerView: 'auto',
    spaceBetween: 8,
    speed,
    watchSlidesProgress: true,
    breakpoints: { 541: { direction: 'vertical' } },
});

const photos = new Swiper(stage, {
    modules: [A11y, Navigation, Thumbs],
    rewind: true,
    speed,
    navigation: {
        prevEl: gallery.querySelector('[data-gallery-prev]'),
        nextEl: gallery.querySelector('[data-gallery-next]'),
        addIcons: false,
    },
    thumbs: { swiper: thumbnails, multipleActiveThumbs: false },
    a11y: {
        prevSlideMessage: 'Попереднє фото',
        nextSlideMessage: 'Наступне фото',
        firstSlideMessage: 'Перше фото',
        lastSlideMessage: 'Останнє фото',
        slideLabelMessage: 'Фото {{index}} з {{slidesLength}}',
    },
    on: {
        slideChange(swiper) {
            links.forEach((link, index) => {
                const isActive = index === swiper.activeIndex;
                zoomLinks[index].tabIndex = isActive ? 0 : -1;
                if (isActive) {
                    link.setAttribute('aria-current', 'true');
                } else {
                    link.removeAttribute('aria-current');
                }
            });
        },
    },
});

links.forEach((link, index) => link.addEventListener('click', event => {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    photos.slideTo(index);
}));

gallery.addEventListener('keydown', event => {
    if (event.altKey || event.ctrlKey || event.metaKey) return;
    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault();
        if (event.key === 'ArrowLeft') photos.slidePrev();
        else photos.slideNext();
    }
});
