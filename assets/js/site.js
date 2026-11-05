/* Small public-site interactions. No libraries, CDN requests, or framework dependency. */
document.addEventListener('DOMContentLoaded', () => {
    const adminMode = document.body.classList.contains('admin-layout');
    const adminShell = adminMode ? document.querySelector('.admin-app') : null;
    const header = document.querySelector('.main-header');
    const toggle = document.querySelector('.nav-toggle');
    const nav = document.getElementById('main-navigation');

    if (header && toggle && nav) {
        const menuIsOpen = () => adminMode && adminShell
            ? adminShell.classList.contains('sidebar-open')
            : header.classList.contains('nav-open');
        const closeMenu = (restoreFocus = false) => {
            if (!menuIsOpen()) return;
            if (adminMode && adminShell) adminShell.classList.remove('sidebar-open');
            else header.classList.remove('nav-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', adminMode ? 'باز کردن منوی مدیریت' : 'باز کردن منوی اصلی');
            if (restoreFocus) toggle.focus();
        };
        toggle.addEventListener('click', () => {
            const open = adminMode && adminShell
                ? adminShell.classList.toggle('sidebar-open')
                : header.classList.toggle('nav-open');
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', open ? 'بستن منو' : (adminMode ? 'باز کردن منوی مدیریت' : 'باز کردن منوی اصلی'));
            if (open && window.matchMedia('(max-width: 900px)').matches) {
                const firstLink = nav.querySelector('a[href]');
                if (firstLink) firstLink.focus();
            }
        });
        nav.addEventListener('click', event => {
            if (event.target.closest('a')) closeMenu();
        });
        document.addEventListener('click', event => {
            if (!adminMode && header.classList.contains('nav-open') && !header.contains(event.target)) closeMenu();
            if (adminMode && adminShell && adminShell.classList.contains('sidebar-open') && (event.target === adminShell || !adminShell.contains(event.target))) closeMenu();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') closeMenu(true);
        });
        window.addEventListener('resize', () => {
            if (window.innerWidth > 900) closeMenu();
        }, { passive: true });
    }

    document.addEventListener('keydown', event => {
        if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return;
        const active = document.activeElement;
        if (active && (active.matches('input,textarea,select,[contenteditable="true"]'))) return;
        const input = document.querySelector('.home-search input[name="q"], .search-form input[name="q"], .search-page-form input[name="q"]');
        if (input) {
            event.preventDefault();
            input.focus();
        }
    });

    const categoryFilter = document.getElementById('category-filter');
    if (!categoryFilter) return;
    const filterBar = categoryFilter.closest('.category-filter-bar');
    const cards = Array.from(document.querySelectorAll('[data-category-card]'));
    const sections = Array.from(document.querySelectorAll('[data-category-section]'));
    const status = document.getElementById('category-filter-status');
    const emptyState = document.getElementById('category-filter-empty');
    const digitMap = {'۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9',
        '٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9'};
    const normalize = value => String(value || '')
        .toLocaleLowerCase('fa-IR')
        .replace(/[يى]/g, 'ی')
        .replace(/ك/g, 'ک')
        .replace(/[\u064b-\u065f\u0670ـ]/g, '')
        .replace(/[۰-۹٠-٩]/g, digit => digitMap[digit] || digit)
        .replace(/\s+/g, ' ')
        .trim();

    if (filterBar) filterBar.hidden = false;
    const applyFilter = () => {
        const terms = normalize(categoryFilter.value).split(' ').filter(Boolean);
        let visibleCount = 0;
        cards.forEach(card => {
            const haystack = normalize((card.dataset.search || '') + ' ' + card.textContent);
            const matches = terms.every(term => haystack.includes(term));
            card.hidden = !matches;
            if (matches) visibleCount++;
        });
        sections.forEach(section => {
            const count = section.querySelectorAll('[data-category-card]:not([hidden])').length;
            section.hidden = count === 0;
            const badge = section.querySelector('[data-category-count]');
            if (badge) badge.textContent = count.toLocaleString('fa-IR') + ' دسته‌بندی';
        });
        if (emptyState) emptyState.hidden = terms.length === 0 || visibleCount > 0;
        if (status) {
            status.textContent = terms.length
                ? visibleCount.toLocaleString('fa-IR') + ' دسته‌بندی پیدا شد.'
                : 'برای پیدا کردن سریع‌تر دسته‌بندی، نام آن را بنویسید.';
        }
    };
    categoryFilter.addEventListener('input', applyFilter);
    categoryFilter.addEventListener('search', applyFilter);
});
