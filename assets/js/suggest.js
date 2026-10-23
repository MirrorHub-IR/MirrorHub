/* Lightweight request-form behavior; intentionally independent of the admin bundle. */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('suggestion-form');
    const linksList = document.getElementById('request-links-list');
    const addLinkButton = document.getElementById('add-request-link');
    const description = document.getElementById('description');
    const descriptionCount = document.getElementById('description-count');
    const persianDigits = '۰۱۲۳۴۵۶۷۸۹';

    const faNumber = value => String(value).replace(/\d/g, digit => persianDigits[digit]);
    if (description && descriptionCount) {
        const updateDescriptionCount = () => {
            descriptionCount.textContent = faNumber(description.value.length);
        };
        description.addEventListener('input', updateDescriptionCount);
        updateDescriptionCount();
    }

    if (!form || !linksList) return;
    let formNotice = null;
    const showError = message => {
        if (!formNotice) {
            formNotice = document.createElement('div');
            formNotice.className = 'alert alert-error suggestion-validation-error';
            formNotice.setAttribute('role', 'alert');
            formNotice.setAttribute('tabindex', '-1');
            form.prepend(formNotice);
        }
        formNotice.textContent = message;
        formNotice.focus();
        formNotice.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };
    const clearError = () => {
        if (formNotice) {
            formNotice.remove();
            formNotice = null;
        }
    };

    const updateRows = () => {
        const rows = Array.from(linksList.querySelectorAll('.request-link-row'));
        rows.forEach((row, index) => {
            const number = row.querySelector('.request-link-index');
            const remove = row.querySelector('.request-link-remove');
            if (number) number.textContent = faNumber(index + 1);
            if (remove) remove.disabled = rows.length <= 1;
        });
    };

    if (addLinkButton) {
        addLinkButton.addEventListener('click', () => {
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
            input.autocomplete = 'url';
            input.setAttribute('aria-label', 'آدرس میرور');

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'request-link-remove btn btn-sm btn-outline';
            remove.setAttribute('aria-label', 'حذف این لینک');
            remove.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="18" x2="18" y2="6"/></svg>';

            row.append(number, input, remove);
            linksList.appendChild(row);
            updateRows();
            input.focus();
            clearError();
        });
    }

    linksList.addEventListener('click', event => {
        const remove = event.target.closest('.request-link-remove');
        if (!remove) return;
        const row = remove.closest('.request-link-row');
        if (row && linksList.querySelectorAll('.request-link-row').length > 1) {
            row.remove();
            updateRows();
            clearError();
        }
    });

    const picker = form.querySelector('.protocol-picker');
    if (picker) {
        const customCheckbox = picker.querySelector('input[name="protocols[]"][value="custom"]');
        const customInput = picker.querySelector('.custom-protocol-input');
        if (customCheckbox && customInput) {
            const syncCustomInput = () => {
                customInput.disabled = !customCheckbox.checked;
                if (!customCheckbox.checked) customInput.value = '';
            };
            customCheckbox.addEventListener('change', () => {
                syncCustomInput();
                if (customCheckbox.checked) customInput.focus();
                clearError();
            });
            syncCustomInput();
        }
        picker.addEventListener('change', clearError);
    }

    form.addEventListener('input', clearError, { passive: true });
    updateRows();

    form.addEventListener('submit', event => {
        const protocols = form.querySelectorAll('input[name="protocols[]"]:checked');
        const links = Array.from(form.querySelectorAll('input[name="urls[]"]'));
        const phone = form.querySelector('#phone');
        const invalidLink = links.find(input => !input.value.trim() || !input.checkValidity());
        const customCheckbox = form.querySelector('input[name="protocols[]"][value="custom"]');
        const customInput = form.querySelector('.custom-protocol-input');

        if (!protocols.length) {
            event.preventDefault();
            showError('حداقل یک پروتکل دسترسی را انتخاب کنید.');
            picker?.querySelector('input[name="protocols[]"]')?.focus();
            return;
        }
        if (!links.length || invalidLink) {
            event.preventDefault();
            showError('لطفاً برای هر ردیف، یک آدرس معتبر وارد کنید.');
            (invalidLink || linksList.querySelector('input[name="urls[]"]'))?.focus();
            return;
        }
        if (customCheckbox?.checked && !customInput?.value.trim()) {
            event.preventDefault();
            showError('نام پروتکل سفارشی را وارد کنید.');
            customInput?.focus();
            return;
        }
        if (!phone?.value.trim()) {
            event.preventDefault();
            showError('شماره همراه برای هماهنگی الزامی است.');
            phone?.focus();
            return;
        }
        clearError();
        const submit = form.querySelector('button[type="submit"]');
        if (submit) {
            submit.disabled = true;
            submit.dataset.originalText = submit.textContent;
            submit.textContent = 'در حال ثبت پیشنهاد…';
        }
    });
});
