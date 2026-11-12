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


    /* Generic upload UX: keyboard focus, drag/drop and clipboard file/image paste. */
    let activeUploadInput = null;
    let activeUploadZone = null;
    const uploadInputs = Array.from(document.querySelectorAll('input[type="file"]'));
    const isUploadInputUsable = input => !!input && input.isConnected && !input.disabled;

    const zoneForInput = input => input ? (input.closest('[data-upload-zone]') || input.parentElement) : null;
    const inputForTarget = target => {
        if (!target || target.nodeType !== 1) return null;
        if (target.matches('input[type="file"]')) return target;
        const zone = target.closest('[data-upload-zone], .mh-upload-zone');
        return zone ? zone.querySelector('input[type="file"]') : null;
    };
    const activateUpload = input => {
        if (!isUploadInputUsable(input)) return;
        activeUploadInput = input;
        activeUploadZone = zoneForInput(input);
    };

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
        if (!isUploadInputUsable(input) || !fileList || !fileList.length) return false;
        const accepted = Array.from(fileList).filter(file => file && matchesFileAccept(file, input));
        const selected = input.multiple ? accepted : accepted.slice(0, 1);
        if (!selected.length) {
            if (typeof window.showAlert === 'function') {
                window.showAlert('نوع فایل با فرمت‌های مجاز این بخش سازگار نیست.', 'warning', 'فرمت نامعتبر');
            }
            return false;
        }
        try {
            if (typeof DataTransfer !== 'function') throw new Error('DataTransfer is unavailable');
            const transfer = new DataTransfer();
            selected.forEach(file => transfer.items.add(file));
            input.files = transfer.files;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            activateUpload(input);
            return true;
        } catch (_) {
            if (typeof window.showAlert === 'function') {
                window.showAlert('مرورگر اجازهٔ اتصال فایل Clipboard به فرم را نداد. از انتخاب فایل یا Drag & Drop استفاده کنید.', 'warning', 'مرورگر پشتیبانی نمی‌کند');
            }
            return false;
        }
    };

    uploadInputs.forEach(input => {
        const zone = zoneForInput(input);
        if (!zone) return;
        zone.classList.add('mh-upload-zone');
        if (!zone.hasAttribute('tabindex')) zone.setAttribute('tabindex', '0');
        if (!zone.hasAttribute('role') && !zone.matches('label')) zone.setAttribute('role', 'group');
        if (input.disabled) return;

        const isBackupDropzone = zone.id === 'backup-dropzone';
        const activate = () => activateUpload(input);
        if (!zone.querySelector('.mh-upload-instructions') && !zone.querySelector('.mh-file-upload-prompt') && !isBackupDropzone) {
            const hint = document.createElement('small');
            hint.className = 'mh-upload-instructions';
            hint.textContent = 'برای انتخاب کلیک یا لمس کنید؛ فایل را اینجا رها کنید یا ناحیه را متمرکز کرده و با Ctrl+V بچسبانید.';
            zone.appendChild(hint);
        }

        zone.addEventListener('pointerdown', activate);
        zone.addEventListener('focusin', activate);
        zone.addEventListener('click', activate);
        input.addEventListener('focus', activate);
        input.addEventListener('change', activate);
        zone.addEventListener('keydown', event => {
            if (event.target !== zone || !['Enter', ' '].includes(event.key)) return;
            event.preventDefault();
            activate();
            input.click();
        });

        if (isBackupDropzone) return; // Backup has a dedicated drop handler that also updates its file summary.
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

    // Keep the intended upload target in sync with both pointer and keyboard navigation.
    document.addEventListener('focusin', event => {
        const targetInput = inputForTarget(event.target);
        if (targetInput) {
            activateUpload(targetInput);
            return;
        }
        if (event.target.matches && event.target.matches('input, textarea, select, [contenteditable="true"]')) {
            activeUploadInput = null;
            activeUploadZone = null;
        }
    }, true);

    document.addEventListener('pointerdown', event => {
        const targetInput = inputForTarget(event.target);
        if (targetInput) {
            activateUpload(targetInput);
            return;
        }
        if (event.target.closest && event.target.closest('input:not([type="file"]), textarea, select, [contenteditable="true"]')) {
            activeUploadInput = null;
            activeUploadZone = null;
        }
    }, true);

    const clipboardFiles = clipboardData => {
        if (!clipboardData) return [];
        const found = [];
        const seen = new Set();
        const addFile = file => {
            if (!file) return;
            const key = [file.name || '', file.size || 0, file.type || ''].join('|');
            if (seen.has(key)) return;
            seen.add(key);
            found.push(file);
        };

        // Chromium exposes image data through ClipboardItem.getAsFile(); other
        // browsers may also populate clipboardData.files. Read both interfaces.
        Array.from(clipboardData.files || []).forEach(addFile);
        Array.from(clipboardData.items || []).forEach(item => {
            const type = (item.type || '').toLowerCase();
            if (item.kind !== 'file' && !type.startsWith('image/')) return;
            try { addFile(item.getAsFile()); } catch (_) {}
        });

        return found.map((file, index) => {
            if (file.name) return file;
            const mime = (file.type || 'image/png').toLowerCase();
            const extension = mime === 'image/jpeg' ? 'jpg' : (mime.split('/')[1] || 'png').replace(/[^a-z0-9]/g, '') || 'png';
            try { return new File([file], 'clipboard-image-' + (index + 1) + '.' + extension, { type: mime }); }
            catch (_) { return file; }
        });
    };

    document.addEventListener('paste', event => {
        // Prefer the zone containing the paste target, then the last intentionally
        // focused/clicked upload zone. Do not guess when there are several inputs.
        const targetInput = inputForTarget(event.target) || inputForTarget(document.activeElement);
        const input = targetInput || activeUploadInput;
        if (!isUploadInputUsable(input)) return;

        const files = clipboardFiles(event.clipboardData);
        if (!files.length) return;
        if (assignUploadFiles(input, files)) event.preventDefault();
    }, true);

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
