const aboutLink = document.querySelector('.site-nav .dropdown > .nav-link[href*="/pro-tsentr"]');

if (aboutLink) {
    const dropdown = aboutLink.closest('.dropdown');
    const menu = dropdown?.querySelector(':scope > .dropdown-menu');

    if (dropdown && menu) {
        aboutLink.classList.remove('dropdown-toggle');
        aboutLink.removeAttribute('data-bs-toggle');
        aboutLink.removeAttribute('role');
        aboutLink.removeAttribute('aria-expanded');

        dropdown.classList.add('nav-item--split');

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'site-nav__submenu-toggle dropdown-toggle';
        toggle.setAttribute('data-bs-toggle', 'dropdown');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Відкрити підменю «Про центр»');

        dropdown.insertBefore(toggle, menu);
    }
}
