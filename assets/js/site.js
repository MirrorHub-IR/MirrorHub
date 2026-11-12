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


    /* Generic upload UX: keyboard focus, drag/drop and paste from the clipboard. */
    let activeUploadInput = null;
    const uploadInputs = Array.from(document.querySelectorAll('input[type="file"]'));

    const matchesFileAccept = (file, input) => {
        const accept = (input.getAttribute('accept') || '').toLowerCase().split(',').map(x => x.trim()).filter(Boolean);
        if (!accept.length) return true;
        const name = (file.name || '').toLowerCase();
        const type = (file.type || '').toLowerCase();
        return accept.some(rule => {
            if (rule.startsWith('.')) return name.endsWith(rule);
            if (rule.endsWith('/*')) return type.startsWith(rule.slice(0, -1));
            return type === rule;
        });
    };

    const assignUploadFiles = (input, fileList) => {
        if (!input || !fileList || !fileList.length) return false;
        const accepted = Array.from(fileList).filter(file => matchesFileAccept(file, input));
        const selected = input.multiple ? accepted : accepted.slice(0, 1);
        if (!selected.length) {
            if (typeof window.showAlert === 'function') {
                window.showAlert('نوع فایل با فرمت‌های مجاز این بخش سازگار نیست.', 'warning', 'فرمت نامعتبر');
            }
            return false;
        }
        try {
            const transfer = new DataTransfer();
            selected.forEach(file => transfer.items.add(file));
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        } catch (_) {
            return false;
        }
    };

    uploadInputs.forEach(input => {
        const zone = input.closest('[data-upload-zone]') || input.parentElement;
        if (!zone) return;
        zone.classList.add('mh-upload-zone');
        if (!zone.hasAttribute('tabindex')) zone.setAttribute('tabindex', '0');
        if (!zone.hasAttribute('role') && !zone.matches('label')) zone.setAttribute('role', 'group');
        if (input.disabled) return;

        const isBackupDropzone = zone.id === 'backup-dropzone';
        if (!zone.querySelector('.mh-upload-instructions') && !zone.querySelector('.mh-file-upload-prompt') && !isBackupDropzone) {
            const hint = document.createElement('small');
            hint.className = 'mh-upload-instructions';
            hint.textContent = 'برای انتخاب کلیک کنید، فایل را اینجا رها کنید یا پس از تمرکز روی این بخش با Ctrl+V بچسبانید.';
            zone.appendChild(hint);
        }

        const activate = () => { activeUploadInput = input; };
        zone.addEventListener('focusin', activate);
        input.addEventListener('focus', activate);
        zone.addEventListener('click', activate);
        zone.addEventListener('keydown', event => {
            if (event.target !== zone || !['Enter', ' '].includes(event.key)) return;
            event.preventDefault();
            input.click();
            activate();
        });

        if (isBackupDropzone) return; // backup-specific handler also updates the selected-file summary.
        ['dragenter', 'dragover'].forEach(type => zone.addEventListener(type, event => {
            event.preventDefault();
            event.stopPropagation();
            activate();
            zone.classList.add('is-dragging');
        }));
        ['dragleave', 'drop'].forEach(type => zone.addEventListener(type, event => {
            event.preventDefault();
            event.stopPropagation();
            if (type === 'drop') assignUploadFiles(input, event.dataTransfer && event.dataTransfer.files);
            zone.classList.remove('is-dragging');
        }));
    });

    document.addEventListener('focusin', event => {
        if (activeUploadInput && !event.target.closest('.mh-upload-zone') && event.target !== activeUploadInput) {
            activeUploadInput = null;
        }
    });

    const clipboardFiles = clipboardData => {
        if (!clipboardData) return [];
        let files = Array.from(clipboardData.files || []);
        if (!files.length && clipboardData.items) {
            files = Array.from(clipboardData.items)
                .filter(item => item.kind === 'file')
                .map(item => {
                    try { return item.getAsFile(); } catch (_) { return null; }
                })
                .filter(Boolean);
        }
        return files.map((file, index) => {
            if (file.name) return file;
            const mime = (file.type || 'image/png').toLowerCase();
            const extension = mime === 'image/jpeg' ? 'jpg' : (mime.split('/')[1] || 'png').replace(/[^a-z0-9]/g, '') || 'png';
            try { return new File([file], 'clipboard-image-' + (index + 1) + '.' + extension, { type: mime }); }
            catch (_) { return file; }
        });
    };

    document.addEventListener('paste', event => {
        if (!activeUploadInput) return;
        const files = clipboardFiles(event.clipboardData);
        if (!files.length) return;
        if (assignUploadFiles(activeUploadInput, files)) event.preventDefault();
    });

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
