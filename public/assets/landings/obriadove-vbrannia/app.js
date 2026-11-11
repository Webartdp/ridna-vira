const viewer = document.querySelector('dialog');
const image = document.querySelector('#viewer-image');
const title = document.querySelector('#viewer-title');

for (const link of document.querySelectorAll('[data-view]')) {
  link.addEventListener('click', (event) => {
    if (typeof viewer.showModal !== 'function') return;
    event.preventDefault();
    image.src = link.href;
    image.alt = link.dataset.view;
    title.textContent = link.dataset.view;
    viewer.showModal();
  });
}
