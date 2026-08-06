const splitNavigationItems = [
    {
        selector: '.site-nav .dropdown > .nav-link[href*="/pro-tsentr"]',
        label: 'Про центр',
    },
    {
        selector: '.site-nav .dropdown > .nav-link[href*="/ridna-vira"]',
        label: 'Рідна Віра',
    },
];

splitNavigationItems.forEach(({ selector, label }) => {
    const parentLink = document.querySelector(selector);

    if (!parentLink) return;

    const dropdown = parentLink.closest('.dropdown');
    const menu = dropdown?.querySelector(':scope > .dropdown-menu');

    if (!dropdown || !menu || dropdown.classList.contains('nav-item--split')) return;

    parentLink.classList.remove('dropdown-toggle');
    parentLink.removeAttribute('data-bs-toggle');
    parentLink.removeAttribute('role');
    parentLink.removeAttribute('aria-expanded');

    dropdown.classList.add('nav-item--split');

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'site-nav__submenu-toggle dropdown-toggle';
    toggle.setAttribute('data-bs-toggle', 'dropdown');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', `Відкрити підменю «${label}»`);

    dropdown.insertBefore(toggle, menu);
});
