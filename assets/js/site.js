/* Small public-site interactions. No libraries, CDN requests, or framework dependency. */
document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('.main-header');
    const toggle = document.querySelector('.nav-toggle');
    const nav = document.getElementById('main-navigation');

    if (header && toggle && nav) {
        const closeMenu = () => {
            header.classList.remove('nav-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'باز کردن منو');
        };
        toggle.addEventListener('click', () => {
            const open = header.classList.toggle('nav-open');
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', open ? 'بستن منو' : 'باز کردن منو');
        });
        nav.addEventListener('click', event => {
            if (event.target.closest('a')) closeMenu();
        });
        document.addEventListener('click', event => {
            if (header.classList.contains('nav-open') && !header.contains(event.target)) closeMenu();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') closeMenu();
        });
        window.addEventListener('resize', () => {
            if (window.innerWidth > 760) closeMenu();
        }, { passive: true });
    }

    document.addEventListener('keydown', event => {
        if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return;
        const active = document.activeElement;
        if (active && (active.matches('input,textarea,select,[contenteditable="true"]'))) return;
        const input = document.querySelector('.search-form input[name="q"]');
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
