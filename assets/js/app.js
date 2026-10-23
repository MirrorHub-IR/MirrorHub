document.addEventListener('DOMContentLoaded', () => {
    /* ============================================================
       SVG Icons (inline)
       ============================================================ */
    const Icons = {
        check: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
        x: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        alert: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        trash: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>',
        question: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    };

    const CloseBtn = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

    /* ============================================================
       Modal System
       ============================================================ */
    const Modal = {
        _current: null,

        open(options = {}) {
            const {
                title = '',
                message = '',
                html = '',
                type = 'info',
                confirmText = 'تأیید',
                cancelText = 'انصراف',
                showCancel = true,
                onConfirm = null,
                onCancel = null,
                wide = false,
                icon = null,
            } = options;

            this.close(true);

            const iconMap = {
                success: Icons.check,
                error: Icons.x,
                warning: Icons.alert,
                danger: Icons.alert,
                info: Icons.info,
                question: Icons.question,
            };

            const iconHtml = icon || iconMap[type] || Icons.info;

            const overlay = document.createElement('div');
            overlay.className = 'mh-modal-overlay';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');

            const bodyHtml = html || (message ? `<p>${message}</p>` : '');

            overlay.innerHTML = `
                <div class="mh-modal${wide ? ' wide' : ''}">
                    <div class="mh-modal-header ${type}">
                        <span class="mh-modal-icon ${type}">${iconHtml}</span>
                        <span class="mh-modal-title">${title}</span>
                        <button type="button" class="mh-modal-close" aria-label="بستن">${CloseBtn}</button>
                    </div>
                    <div class="mh-modal-body">${bodyHtml}</div>
                    <div class="mh-modal-actions">
                        ${showCancel ? `<button type="button" class="btn btn-outline" data-modal-cancel>${cancelText}</button>` : ''}
                        <button type="button" class="btn btn-primary" data-modal-confirm>${confirmText}</button>
                    </div>
                </div>
            `;

            document.body.appendChild(overlay);
            document.body.style.overflow = 'hidden';
            this._current = overlay;

            requestAnimationFrame(() => {
                const confirmBtn = overlay.querySelector('[data-modal-confirm]');
                if (confirmBtn) confirmBtn.focus();
            });

            const close = (confirmed) => {
                if (confirmed && typeof onConfirm === 'function') {
                    const result = onConfirm(overlay);
                    if (result === false) return;
                } else if (!confirmed && typeof onCancel === 'function') {
                    onCancel();
                }
                this.close();
            };

            overlay.querySelector('.mh-modal-close').addEventListener('click', () => close(false));
            overlay.querySelector('[data-modal-confirm]').addEventListener('click', () => close(true));

            const cancelBtn = overlay.querySelector('[data-modal-cancel]');
            if (cancelBtn) cancelBtn.addEventListener('click', () => close(false));

            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) close(false);
            });

            const escHandler = (e) => {
                if (e.key === 'Escape') {
                    document.removeEventListener('keydown', escHandler);
                    close(false);
                }
            };
            document.addEventListener('keydown', escHandler);

            return overlay;
        },

        close(immediate = false) {
            if (!this._current) return;
            const overlay = this._current;
            this._current = null;

            if (immediate) {
                overlay.remove();
                document.body.style.overflow = '';
                return;
            }

            overlay.classList.add('closing');
            document.body.style.overflow = '';
            setTimeout(() => overlay.remove(), 220);
        },
    };

    window.MHModal = Modal;

    window.showAlert = (message, type = 'info', title = null) => {
        const titles = {
            success: 'انجام شد',
            error: 'خطا',
            warning: 'هشدار',
            info: 'اطلاع',
        };
        Modal.open({
            title: title || titles[type] || 'اطلاع',
            message,
            type,
            confirmText: 'باشه',
            showCancel: false,
        });
    };

    window.showConfirm = (options = {}) => {
        return new Promise((resolve) => {
            Modal.open({
                title: options.title || 'تأیید عملیات',
                message: options.message || 'آیا از انجام این عملیات مطمئن هستید؟',
                type: options.type || 'question',
                confirmText: options.confirmText || 'بله، تأیید',
                cancelText: options.cancelText || 'انصراف',
                onConfirm: () => resolve(true),
                onCancel: () => resolve(false),
            });
        });
    };

    /* ============================================================
       Delete Forms with data-confirm
       ============================================================ */
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', async (e) => {
            if (form.dataset.confirmed === '1') return;
            e.preventDefault();
            const message = form.dataset.confirm || 'آیا از حذف مطمئن هستید؟';
            const confirmed = await showConfirm({
                title: 'تأیید حذف',
                message,
                type: 'danger',
                confirmText: 'بله، حذف کن',
                cancelText: 'انصراف',
            });
            if (confirmed) {
                form.dataset.confirmed = '1';
                form.submit();
            }
        });
    });

    /* Fallback: legacy onsubmit confirm */
    document.querySelectorAll('form[onsubmit]').forEach(form => {
        form.addEventListener('submit', async (e) => {
            if (form.dataset.confirmed === '1') return;
            e.preventDefault();
            const message = form.dataset.confirm || 'آیا از انجام این عملیات مطمئن هستید؟';
            const confirmed = await showConfirm({
                title: 'تأیید عملیات',
                message,
                type: 'danger',
                confirmText: 'بله، انجام بده',
            });
            if (confirmed) {
                form.dataset.confirmed = '1';
                form.submit();
            }
        });
        form.removeAttribute('onsubmit');
    });

    /* ============================================================
       Auto-dismiss Alerts
       ============================================================ */
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            alert.style.pointerEvents = 'none';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });

    /* ============================================================
       Category Suggestion Chips
       ============================================================ */
    const chips = document.querySelectorAll('.suggestion-chip');
    const categoryInput = document.getElementById('category_name');
    if (chips.length > 0 && categoryInput) {
        chips.forEach(chip => {
            chip.addEventListener('click', () => {
                categoryInput.value = chip.dataset.value;
                chips.forEach(c => {
                    c.style.borderColor = '';
                    c.style.color = '';
                    c.style.background = '';
                });
                chip.style.borderColor = 'var(--accent)';
                chip.style.color = 'var(--accent)';
                chip.style.background = 'var(--accent-soft)';
                categoryInput.focus();
            });
        });
        categoryInput.addEventListener('input', () => {
            const v = categoryInput.value.trim();
            chips.forEach(c => {
                const m = c.dataset.value === v;
                c.style.borderColor = m ? 'var(--accent)' : '';
                c.style.color = m ? 'var(--accent)' : '';
                c.style.background = m ? 'var(--accent-soft)' : '';
            });
        });
    }

    /* ============================================================
       Suggestion Parent/Child Category Picker
       ============================================================ */
    const parentCategory = document.getElementById('parent_category_id');
    const childCategory = document.getElementById('category_id');
    if (parentCategory && childCategory) {
        const syncChildCategories = () => {
            const parentId = parentCategory.value;
            let visible = 0;
            childCategory.querySelectorAll('option[data-parent]').forEach(option => {
                const show = !!parentId && option.dataset.parent === parentId;
                option.hidden = !show;
                if (!show && option.selected) option.selected = false;
                if (show) visible++;
            });
            childCategory.disabled = !parentId || visible === 0;
            if (!parentId) {
                childCategory.value = '';
                childCategory.querySelector('option:not([data-parent])').textContent = 'ابتدا والد را انتخاب کنید...';
            } else {
                childCategory.querySelector('option:not([data-parent])').textContent =
                    visible ? 'انتخاب زیر‌دسته...' : 'این والد زیر‌دسته‌ای ندارد';
            }
        };
        parentCategory.addEventListener('change', syncChildCategories);
        syncChildCategories();
    }

    /* ============================================================
       Auto Slug Generation
       ============================================================ */
    const nameFa = document.getElementById('name_fa');
    const nameEn = document.getElementById('name_en');
    const slugField = document.getElementById('slug');

    if (slugField) {
        let slugTouched = slugField.value.trim() !== '';
        slugField.addEventListener('input', () => { slugTouched = true; });

        const generateSlug = () => {
            if (slugTouched) return;
            const source = (nameEn && nameEn.value.trim()) || (nameFa && nameFa.value.trim()) || '';
            slugField.value = source
                .trim()
                .toLowerCase()
                .replace(/[^\p{L}\d]+/gu, '-')
                .replace(/^-+|-+$/g, '');
        };

        if (nameFa) nameFa.addEventListener('input', generateSlug);
        if (nameEn) nameEn.addEventListener('input', generateSlug);
    }

    /* ============================================================
       Category Picker Validation
       ============================================================ */
    const categoryForm = document.querySelector('form[data-require-category]:not(.mirror-editor-form)');
    if (categoryForm) {
        categoryForm.addEventListener('submit', (e) => {
            const checked = categoryForm.querySelectorAll('input[name="category_ids[]"]:checked');
            if (checked.length === 0) {
                e.preventDefault();
                showAlert('حداقل یک دسته‌بندی را انتخاب کنید.', 'warning', 'انتخاب دسته‌بندی');
            }
        });
    }

    /* ============================================================
       Smart Form Enhancements
       ============================================================ */
    const description = document.getElementById('description');
    const descriptionCount = document.getElementById('description-count');
    if (description && descriptionCount) {
        const renderCount = () => {
            descriptionCount.textContent = String(description.value.length).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
        };
        description.addEventListener('input', renderCount);
        renderCount();
    }

    const suggestionForm = document.getElementById('suggestion-form');
    const requestLinksList = document.getElementById('request-links-list');
    const addRequestLink = document.getElementById('add-request-link');

    if (suggestionForm && requestLinksList) {
        const updateRequestLinkRows = () => {
            const rows = Array.from(requestLinksList.querySelectorAll('.request-link-row'));
            rows.forEach((row, index) => {
                const number = row.querySelector('.request-link-index');
                const remove = row.querySelector('.request-link-remove');
                if (number) number.textContent = String(index + 1).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
                if (remove) remove.disabled = rows.length === 1;
            });
        };

        if (addRequestLink) {
            addRequestLink.addEventListener('click', () => {
                const row = document.createElement('div');
                row.className = 'request-link-row';

                const number = document.createElement('span');
                number.className = 'request-link-index';

                const input = document.createElement('input');
                input.type = 'url';
                input.name = 'urls[]';
                input.required = true;
                input.maxLength = 1000;
                input.placeholder = 'https://example.com/mirror';
                input.dir = 'ltr';
                input.autocomplete = 'off';

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'request-link-remove btn btn-sm btn-outline';
                remove.setAttribute('aria-label', 'حذف لینک');
                remove.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

                row.append(number, input, remove);
                requestLinksList.appendChild(row);
                updateRequestLinkRows();
                input.focus();
            });
        }

        requestLinksList.addEventListener('click', (e) => {
            const remove = e.target.closest('.request-link-remove');
            if (!remove) return;
            const row = remove.closest('.request-link-row');
            if (row && requestLinksList.querySelectorAll('.request-link-row').length > 1) {
                row.remove();
                updateRequestLinkRows();
            }
        });

        updateRequestLinkRows();

        suggestionForm.addEventListener('submit', (e) => {
            const protocols = suggestionForm.querySelectorAll('input[name="protocols[]"]:checked');
            const links = suggestionForm.querySelectorAll('input[name="urls[]"]');
            const phone = document.getElementById('phone');
            const hasInvalidLink = Array.from(links).some(input => !input.checkValidity() || !input.value.trim());
            if (!protocols.length || !links.length || hasInvalidLink || !phone?.value.trim()) {
                e.preventDefault();
                showAlert('حداقل یک لینک معتبر، یک پروتکل و شماره همراه الزامی است.', 'warning', 'اطلاعات ناقص');
            }
        });
    }

    /* ============================================================
       Multi Protocol Picker
       ============================================================ */
    document.querySelectorAll('.protocol-picker').forEach(picker => {
        const customCheckbox = picker.querySelector('input[name="protocols[]"][value="custom"]');
        const customInput = picker.querySelector('.custom-protocol-input');
        if (customCheckbox && customInput) {
            const sync = () => {
                customInput.disabled = !customCheckbox.checked;
                if (!customCheckbox.checked) customInput.value = '';
                else customInput.focus();
            };
            customCheckbox.addEventListener('change', sync);
            sync();
        }
    });

    /* ============================================================
       Backup Import UX
       ============================================================ */
    const backupForm = document.getElementById('backup-import-form');
    const backupFile = document.getElementById('backup-file');
    const backupDropzone = document.getElementById('backup-dropzone');
    const backupFilePill = document.getElementById('backup-file-pill');
    const backupFileName = document.getElementById('backup-file-name');
    const backupFileSize = document.getElementById('backup-file-size');
    const backupSubmit = document.getElementById('backup-submit');
    const replaceConfirm = document.getElementById('replace-confirm');

    if (backupForm && backupFile && backupSubmit) {
        const mergeMode = backupForm.querySelector('input[name="restore_mode"][value="merge"]');
        const replaceMode = backupForm.querySelector('input[name="restore_mode"][value="replace"]');
        const confirmInput = backupForm.querySelector('input[name="replace_confirm"]');

        const formatSize = bytes => {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1024 / 1024).toFixed(1) + ' MB';
        };

        const syncState = () => {
            const hasFile = backupFile.files && backupFile.files.length > 0;
            const isReplace = replaceMode && replaceMode.checked;
            if (replaceConfirm) replaceConfirm.style.display = isReplace ? 'flex' : 'none';
            if (confirmInput && !isReplace) confirmInput.checked = false;
            backupSubmit.disabled = !hasFile || (isReplace && !confirmInput?.checked);
        };

        const showFile = file => {
            if (!file || !file.name.toLowerCase().endsWith('.json')) {
                backupFile.value = '';
                if (backupFilePill) backupFilePill.classList.remove('active');
                showAlert('فقط فایل JSON پذیرفته می‌شود.', 'warning', 'فرمت نامعتبر');
                syncState();
                return;
            }
            if (file.size > 25 * 1024 * 1024) {
                backupFile.value = '';
                if (backupFilePill) backupFilePill.classList.remove('active');
                showAlert('حجم فایل نباید بیشتر از ۲۵ مگابایت باشد.', 'warning', 'فایل بزرگ است');
                syncState();
                return;
            }
            if (backupFileName) backupFileName.textContent = file.name;
            if (backupFileSize) backupFileSize.textContent = formatSize(file.size);
            if (backupFilePill) backupFilePill.classList.add('active');
            syncState();
        };

        backupFile.addEventListener('change', () => showFile(backupFile.files[0]));
        [mergeMode, replaceMode, confirmInput].forEach(el => el && el.addEventListener('change', syncState));

        if (backupDropzone) {
            ['dragenter','dragover'].forEach(type => backupDropzone.addEventListener(type, e => {
                e.preventDefault();
                backupDropzone.classList.add('dragover');
            }));
            ['dragleave','drop'].forEach(type => backupDropzone.addEventListener(type, e => {
                e.preventDefault();
                backupDropzone.classList.remove('dragover');
            }));
            backupDropzone.addEventListener('drop', e => {
                const file = e.dataTransfer.files && e.dataTransfer.files[0];
                if (!file) return;
                try {
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    backupFile.files = dt.files;
                } catch (_) {}
                showFile(file);
            });
        }

        backupForm.addEventListener('submit', async e => {
            if (!replaceMode?.checked || backupForm.dataset.confirmed === '1') return;
            e.preventDefault();
            const confirmed = await showConfirm({
                title: 'تأیید جایگزینی کامل',
                message: 'این عملیات تمام داده‌های فعلی MirrorHub را حذف می‌کند. ادامه می‌دهید؟',
                type: 'danger',
                confirmText: 'بله، جایگزین کن',
                cancelText: 'انصراف'
            });
            if (confirmed) {
                backupForm.dataset.confirmed = '1';
                backupForm.submit();
            }
        });

        syncState();
    }

    /* ============================================================
       Mirror Editor v2 UX
       ============================================================ */
    const mirrorForm = document.querySelector('.mirror-form-shell');
    if (mirrorForm && document.querySelector('.mirror-form-page')) {
        const q = selector => mirrorForm.querySelector(selector);
        const qa = selector => Array.from(mirrorForm.querySelectorAll(selector));
        const mirrorSubmit = q('button[type="submit"]');
        const slugField = q('#slug');
        let slugTouched = !!slugField?.value.trim();
        let formDirty = false;

        const nameFaField = q('#name_fa');
        const nameEnField = q('#name_en');
        const urlField = q('#url');
        const statusField = q('#status');
        const descriptionField = q('#description');
        const customCheckbox = q('input[name="protocols[]"][value="custom"]');
        const customPanel = document.getElementById('custom-protocol-panel');
        const customInput = q('input[name="custom_protocol"], input[name="custom_protocols[]"]');

        const previewName = document.getElementById('preview-name');
        const previewNameEn = document.getElementById('preview-name-en');
        const previewUrl = document.getElementById('preview-url');
        const previewDescription = document.getElementById('preview-description');
        const previewProtocols = document.getElementById('preview-protocols');
        const previewStatus = document.getElementById('preview-status');

        const protocolCount = document.getElementById('protocol-selected-count');
        const categoryCount = document.getElementById('category-selected-count');

        const checks = {
            name: document.getElementById('check-name'),
            url: document.getElementById('check-url'),
            protocol: document.getElementById('check-protocol'),
            category: document.getElementById('check-category'),
        };

        const faNumber = value => String(value).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);

        const setCheck = (el, ok) => {
            if (!el) return;
            el.classList.toggle('ok', !!ok);
            const dot = el.querySelector('span');
            if (dot) dot.innerHTML = ok ? Icons.check : '';
        };

        const syncPreview = () => {
            const fa = nameFaField?.value.trim() || 'نام میرور';
            const en = nameEnField?.value.trim() || 'Mirror Name';
            const url = urlField?.value.trim() || 'https://example.com/mirror';
            const desc = descriptionField?.value.trim() || 'توضیحات میرور در اینجا نمایش داده می‌شود.';
            const selected = qa('input[name="protocols[]"]:checked');
            const categories = qa('input[name="category_ids[]"]:checked');
            const statusActive = statusField?.value !== 'inactive';

            if (previewName) previewName.textContent = fa;
            if (previewNameEn) previewNameEn.textContent = en;
            if (previewUrl) previewUrl.textContent = url;
            if (previewDescription) previewDescription.textContent = desc;
            if (previewStatus) {
                previewStatus.textContent = statusActive ? 'فعال' : 'غیرفعال';
                previewStatus.className = 'badge ' + (statusActive ? 'badge-success' : 'badge-warning');
            }
            if (previewProtocols) {
                previewProtocols.innerHTML = selected.length
                    ? selected.map(input => {
                        const label = input.closest('.mirror-protocol-card')?.querySelector('.mirror-protocol-main strong')?.textContent.trim() || input.value.toUpperCase();
                        if (input.value === 'custom') {
                            return customInput?.value.trim()
                                ? '<span class="badge badge-default protocol-badge">' + escapeHtml(customInput.value.trim().toUpperCase()) + '</span>'
                                : '';
                        }
                        return '<span class="badge ' + ({
                            https: 'badge-success',
                            http: 'badge-warning',
                            ftp: 'badge-info',
                            rsync: 'badge-primary'
                        }[input.value] || 'badge-default') + ' protocol-badge">' + escapeHtml(label.toUpperCase()) + '</span>';
                    }).join('') || '<span class="badge badge-default">پروتکلی انتخاب نشده</span>'
                    : '<span class="badge badge-default">پروتکلی انتخاب نشده</span>';
            }

            if (protocolCount) protocolCount.textContent = faNumber(selected.length) + ' انتخاب';
            if (categoryCount) categoryCount.textContent = faNumber(categories.length) + ' انتخاب';

            setCheck(checks.name, !!nameFaField?.value.trim());
            setCheck(checks.url, !!urlField?.value.trim() && !!urlField?.checkValidity());
            const customValid = !customCheckbox?.checked || !!customInput?.value.trim();
            setCheck(checks.protocol, selected.length > 0 && customValid);
            setCheck(checks.category, categories.length > 0);
        };

        const syncCustomProtocol = () => {
            const active = !!customCheckbox?.checked;
            if (customPanel) customPanel.classList.toggle('is-visible', active);
            if (customInput) {
                customInput.disabled = !active;
                if (!active) customInput.value = '';
            }
            syncPreview();
        };

        const markDirty = () => {
            formDirty = true;
        };

        [nameFaField, nameEnField, urlField, statusField, descriptionField, customInput].forEach(field => {
            if (!field) return;
            field.addEventListener('input', () => {
                markDirty();
                syncPreview();
            });
            field.addEventListener('change', () => {
                markDirty();
                syncPreview();
            });
        });

        qa('input[name="protocols[]"], input[name="category_ids[]"]').forEach(input => {
            input.addEventListener('change', () => {
                markDirty();
                syncPreview();
            });
        });

        if (slugField) {
            slugField.addEventListener('input', () => {
                slugTouched = true;
                markDirty();
            });

            const slugifyClient = value => String(value)
                .normalize('NFKD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '')
                .slice(0, 160);

            const autoSlug = () => {
                if (slugTouched || slugField.value.trim()) return;
                const source = nameEnField?.value.trim() || '';
                const generated = slugifyClient(source);
                if (generated) slugField.value = generated;
            };

            nameEnField?.addEventListener('blur', autoSlug);
        }

        customCheckbox?.addEventListener('change', () => {
            markDirty();
            syncCustomProtocol();
        });

        syncCustomProtocol();
        syncPreview();

        mirrorForm.addEventListener('submit', e => {
            const protocolInputs = qa('input[name="protocols[]"]:checked');
            const categoryInputs = qa('input[name="category_ids[]"]:checked');
            const customValid = !customCheckbox?.checked || !!customInput?.value.trim();

            if (!nameFaField?.value.trim() || !urlField?.checkValidity() || !protocolInputs.length || !customValid || !categoryInputs.length) {
                e.preventDefault();
                syncPreview();
                showAlert('نام، آدرس معتبر، حداقل یک پروتکل و حداقل یک دسته‌بندی الزامی است.', 'warning', 'اطلاعات ناقص');
                return;
            }

            formDirty = false;
            if (mirrorSubmit) {
                mirrorSubmit.disabled = true;
                mirrorSubmit.dataset.label = mirrorSubmit.innerHTML;
                mirrorSubmit.innerHTML = '<span class="mirror-save-spinner" aria-hidden="true"></span> در حال ذخیره...';
            }
        });

        window.addEventListener('beforeunload', e => {
            if (!formDirty) return;
            e.preventDefault();
            e.returnValue = '';
        });
    }

    /* ============================================================
       Responsive Navigation
       ============================================================ */
    const mainHeader = document.querySelector('.main-header');
    const navToggle = document.querySelector('.nav-toggle');
    const mainNav = document.getElementById('main-navigation');

    if (mainHeader && navToggle && mainNav) {
        const closeNavigation = () => {
            mainHeader.classList.remove('nav-open');
            navToggle.setAttribute('aria-expanded', 'false');
            navToggle.setAttribute('aria-label', 'باز کردن منو');
        };

        navToggle.addEventListener('click', () => {
            const open = mainHeader.classList.toggle('nav-open');
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            navToggle.setAttribute('aria-label', open ? 'بستن منو' : 'باز کردن منو');
        });

        mainNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', closeNavigation);
        });

        document.addEventListener('click', e => {
            if (mainHeader.classList.contains('nav-open') && !mainHeader.contains(e.target)) {
                closeNavigation();
            }
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeNavigation();
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 760) closeNavigation();
        });
    }

    /* ============================================================
       Focus Search on '/' key
       ============================================================ */
    document.addEventListener('keydown', (e) => {
        if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
            const searchInput = document.querySelector('.search-form input[name="q"]');
            if (searchInput) {
                e.preventDefault();
                searchInput.focus();
            }
        }
    });

    /* ============================================================
       Review Request Modal
       ============================================================ */
    document.querySelectorAll('[data-review-request]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            const nameFa = btn.dataset.nameFa || '';
            const nameEn = btn.dataset.nameEn || '';
            const url = btn.dataset.url || '';
            const protocols = (() => {
                try { return JSON.parse(btn.dataset.protocols || '[]'); } catch (e) { return []; }
            })();
            const description = btn.dataset.description || '';
            const status = btn.dataset.status || 'pending';
            const note = btn.dataset.note || '';
            const mirrorId = btn.dataset.mirrorId || '';
            const csrf = btn.dataset.csrf || '';

            const isReviewed = status === 'reviewed';

            const html = `
                <div style="display:flex;flex-direction:column;gap:0.9rem;">
                    <div>
                        <p style="font-size:0.78rem;color:var(--text-muted);margin-bottom:0.15rem;">نام میرور</p>
                        <p style="color:var(--text-primary);font-weight:600;font-size:0.95rem;">${escapeHtml(nameFa)}</p>
                        ${nameEn ? `<p style="font-family:monospace;direction:ltr;text-align:right;font-size:0.8rem;color:var(--text-muted);">${escapeHtml(nameEn)}</p>` : ''}
                    </div>
                    <div>
                        <p style="font-size:0.78rem;color:var(--text-muted);margin-bottom:0.15rem;">آدرس</p>
                        <a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer" style="font-family:monospace;direction:ltr;text-align:right;font-size:0.82rem;word-break:break-all;">${escapeHtml(url)}</a>
                    </div>
                    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                        ${protocols.map(item => { const label = item.custom_label || item.protocol || ''; return label ? '<span class="badge protocol-badge">' + escapeHtml(label.toUpperCase()) + '</span>' : ''; }).join('')}
                    </div>
                    ${description ? `<div><p style="font-size:0.78rem;color:var(--text-muted);margin-bottom:0.15rem;">توضیحات</p><p style="font-size:0.88rem;line-height:1.7;">${escapeHtml(description)}</p></div>` : ''}
                    <form id="review-form-${id}" method="POST" action="?id=${id}" style="display:flex;flex-direction:column;gap:0.7rem;margin-top:0.4rem;">
                        <input type="hidden" name="csrf_token" value="${escapeHtml(csrf)}">
                        <label class="review-toggle">
                            <input type="checkbox" name="reviewed" value="1" ${isReviewed ? 'checked' : ''}>
                            <span>بررسی شد</span>
                        </label>
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="note-${id}" style="font-size:0.85rem;">یادداشت مدیر</label>
                            <textarea id="note-${id}" name="admin_note" rows="3" maxlength="500" placeholder="یادداشت اختیاری...">${escapeHtml(note)}</textarea>
                        </div>
                    </form>
                </div>
            `;

            const overlay = Modal.open({
                title: 'رسیدگی به درخواست',
                html,
                type: isReviewed ? 'success' : 'info',
                wide: true,
                confirmText: isReviewed ? 'ذخیره یادداشت' : 'علامت‌گذاری بررسی‌شده',
                cancelText: 'بستن',
                onConfirm: (ov) => {
                    const form = ov.querySelector(`#review-form-${id}`);
                    if (!form) return false;

                    const checkbox = form.querySelector('input[name="reviewed"]');
                    const wasReviewed = checkbox.checked;

                    // Append the action hidden input based on checkbox state
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'req_action';
                    actionInput.value = wasReviewed ? 'mark_reviewed' : 'mark_pending';
                    form.appendChild(actionInput);

                    form.submit();
                    return false;
                },
            });

            // Custom footer buttons if user wants to create mirror
            const footer = overlay.querySelector('.mh-modal-actions');
            if (isReviewed && !mirrorId) {
                const createBtn = document.createElement('a');
                createBtn.className = 'btn btn-success';
                createBtn.style.marginLeft = 'auto';
                createBtn.href = `${window.MH_SITE_URL || ''}/admin/mirrors.php?action=add&from_request=${id}`;
                createBtn.textContent = 'ساخت میرور از این درخواست';
                footer.insertBefore(createBtn, footer.firstChild);
            } else if (mirrorId) {
                const viewBtn = document.createElement('a');
                viewBtn.className = 'btn btn-success';
                viewBtn.style.marginLeft = 'auto';
                viewBtn.href = `${window.MH_SITE_URL || ''}/admin/mirrors.php?action=edit&id=${mirrorId}`;
                viewBtn.textContent = 'مشاهده میرور ساخته‌شده';
                footer.insertBefore(viewBtn, footer.firstChild);
            }
        });
    });

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /* ============================================================
       Homepage category filter (progressive enhancement)
       ============================================================ */
    const categoryFilter = document.getElementById('category-filter');
    if (categoryFilter) {
        const filterBar = categoryFilter.closest('.category-filter-bar');
        if (filterBar) filterBar.hidden = false;
        const cards = Array.from(document.querySelectorAll('[data-category-card]'));
        const sections = Array.from(document.querySelectorAll('[data-category-section]'));
        const status = document.getElementById('category-filter-status');
        const emptyState = document.getElementById('category-filter-empty');

        const applyCategoryFilter = () => {
            const query = categoryFilter.value.trim().toLocaleLowerCase();
            let visible = 0;
            cards.forEach(card => {
                const haystack = (card.dataset.search || card.textContent || '').toLocaleLowerCase();
                const match = !query || haystack.includes(query);
                card.hidden = !match;
                if (match) visible++;
            });
            sections.forEach(section => {
                const visibleCards = Array.from(section.querySelectorAll('[data-category-card]:not([hidden])'));
                section.hidden = visibleCards.length === 0;
                const countBadge = section.querySelector('[data-category-count]');
                if (countBadge) countBadge.textContent = visibleCards.length.toLocaleString('fa-IR') + ' دسته‌بندی';
            });
            if (emptyState) emptyState.hidden = !query || visible > 0;
            if (status) {
                status.textContent = query
                    ? (visible.toLocaleString('fa-IR') + ' دسته‌بندی پیدا شد.')
                    : 'برای پیدا کردن سریع‌تر دسته‌بندی، نام آن را بنویسید.';
            }
        };

        categoryFilter.addEventListener('input', applyCategoryFilter);
        categoryFilter.addEventListener('search', applyCategoryFilter);
    }

});