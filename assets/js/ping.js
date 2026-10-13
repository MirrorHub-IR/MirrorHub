/* ============================================================
   MirrorHub — پینگ سمت کاربر (Client-side latency probe)
   ------------------------------------------------------------
   «زمان پاسخ» هر لینک از داخل مرورگر خود کاربر اندازه‌گیری می‌شود،
   نه از سمت سرور/میزبان. هیچ درخواستی از سرور ما به میرورها زده
   نمی‌شود و نتیجه دقیقاً همان چیزی است که کاربر تجربه می‌کند.

   مرورگر نمی‌تواند بسته ICMP (پینگ واقعی) بفرستد، پس نزدیک‌ترین
   معادل، زمان رفت‌وبرگشت یک درخواست HTTP تا دریافت پاسخ است.

   یک اندازه‌گیری انجام می‌شود و نتیجه روی خود دکمه به شکل «۱۲۳ ms»
   با رنگ سطح سرعت نمایش داده می‌شود. توضیح کامل در tooltip دکمه است.
   ============================================================ */
(function () {
    'use strict';

    var TIMEOUT = 9000;     // حداکثر انتظار برای پاسخ (میلی‌ثانیه)

    var LEVELS = [
        { key: 'excellent', max: 150,      label: 'عالی' },
        { key: 'good',      max: 350,      label: 'خوب' },
        { key: 'fair',      max: 800,      label: 'کند' },
        { key: 'slow',      max: Infinity, label: 'خیلی کند' }
    ];

    var STATE_CLASSES = ['is-busy', 'ping-excellent', 'ping-good', 'ping-fair', 'ping-slow', 'ping-fail', 'ping-na'];

    function faNum(value) {
        try {
            return new Intl.NumberFormat('fa-IR').format(value);
        } catch (e) {
            return String(value);
        }
    }

    function faMs(value) {
        return faNum(Math.round(value)) + ' میلی‌ثانیه';
    }

    function resolveUrl(raw) {
        try {
            return new URL(raw, window.location.href);
        } catch (e) {
            return null;
        }
    }

    /* یک اندازه‌گیری: زمان تا دریافت پاسخ (هدرهای HTTP).
       mode: no-cors تا پاسخ‌های بدون هدر CORS هم قابل اندازه‌گیری باشند
       (در این حالت پاسخ opaque است و فقط «رسیدن پاسخ» قابل تشخیص است).
       به‌محض دریافت پاسخ، درخواست را قطع می‌کنیم تا بدنه فایل دانلود نشود. */
    function probeOnce(target, timeout) {
        return new Promise(function (resolve) {
            var controller = new AbortController();
            var started = performance.now();
            var settled = false;

            var timer = setTimeout(function () {
                if (settled) return;
                settled = true;
                try { controller.abort(); } catch (e) {}
                resolve({ ok: false, reason: 'timeout', ms: performance.now() - started });
            }, timeout);

            fetch(target.href, {
                method: 'GET',
                mode: 'no-cors',
                cache: 'no-store',
                credentials: 'omit',
                redirect: 'follow',
                referrerPolicy: 'no-referrer',
                signal: controller.signal
            }).then(function (response) {
                if (settled) return;
                settled = true;
                clearTimeout(timer);
                var ms = performance.now() - started;
                var status = response.type === 'opaque' ? null : response.status;
                try { controller.abort(); } catch (e) {}
                resolve({ ok: true, ms: ms, status: status });
            }).catch(function (error) {
                if (settled) return;
                settled = true;
                clearTimeout(timer);
                var aborted = error && error.name === 'AbortError';
                resolve({
                    ok: false,
                    reason: aborted ? 'aborted' : 'network',
                    ms: performance.now() - started
                });
            });
        });
    }

    /* بررسی‌های پیش از اندازه‌گیری: مواردی که مرورگر اجازه یا توانایی
       اندازه‌گیری آن‌ها را ندارد و باید صادقانه گزارش شوند.
       برمی‌گرداند { label, reason } یا null. */
    function preflight(button, target) {
        if (!target) {
            return { label: 'نامعتبر', reason: 'آدرس این لینک قابل استفاده در مرورگر نیست.' };
        }
        if (button.dataset.pingUnsupported === '1' ||
            (target.protocol !== 'http:' && target.protocol !== 'https:')) {
            return {
                label: 'قابل پینگ نیست',
                reason: 'این نوع لینک (پروتکل) در مرورگر قابل اندازه‌گیری نیست؛ پینگ فقط برای لینک‌های HTTP و HTTPS امکان‌پذیر است.'
            };
        }
        if (window.location.protocol === 'https:' && target.protocol === 'http:') {
            return {
                label: 'مسدود',
                reason: 'این صفحه روی HTTPS باز شده و مرورگر اجازه اتصال به لینک HTTP را نمی‌دهد (محتوای ناامن مسدود می‌شود).'
            };
        }
        if (navigator.onLine === false) {
            return { label: 'آفلاین', reason: 'اتصال اینترنت شما قطع است.' };
        }
        return null;
    }

    function levelFor(ms) {
        for (var i = 0; i < LEVELS.length; i++) {
            if (ms <= LEVELS[i].max) return LEVELS[i];
        }
        return LEVELS[LEVELS.length - 1];
    }

    /* فقط متن دکمه عوض می‌شود (نه محتوای کل دکمه) تا آیکون SVG باقی بماند. */
    function setState(button, state, text) {
        STATE_CLASSES.forEach(function (cls) { button.classList.remove(cls); });
        if (state) button.classList.add(state);

        var label = button.querySelector('.ping-btn-text');
        if (label && text != null) label.textContent = text;
    }

    function successTitle(target, ms, level, status) {
        var lines = [
            'زمان پاسخ: ' + faMs(ms) + ' (' + level.label + ')',
            'لینک: ' + target.href
        ];

        if (status != null) {
            lines.push('کد پاسخ HTTP: ' + faNum(status));
        }

        lines.push('این عدد زمان رفت‌وبرگشت یک درخواست HTTP از مرورگر شماست؛ نزدیک‌ترین معادل پینگ در مرورگر. سرور ما در این اندازه‌گیری دخالتی ندارد.');
        lines.push('برای گرفتن عدد تازه دوباره کلیک کنید.');
        return lines.join('\n');
    }

    function runPing(button) {
        if (button.dataset.pingBusy === '1') return;

        var target = resolveUrl(button.dataset.pingUrl || '');
        var problem = preflight(button, target);

        if (problem) {
            setState(button, 'ping-na', problem.label);
            button.title = problem.reason;
            return;
        }

        button.dataset.pingBusy = '1';
        button.setAttribute('aria-busy', 'true');
        setState(button, 'is-busy', '…');
        button.title = 'در حال اندازه‌گیری زمان پاسخ از مرورگر شما…';

        probeOnce(target, TIMEOUT).then(function (result) {
            button.dataset.pingBusy = '0';
            button.removeAttribute('aria-busy');

            if (!result.ok) {
                var reason = result.reason === 'timeout'
                    ? 'در بازه ' + faNum(Math.round(TIMEOUT / 1000)) + ' ثانیه‌ای پاسخی از سرور دریافت نشد.'
                    : 'اتصال برقرار نشد؛ ممکن است سرور در دسترس نباشد، گواهی SSL/DNS نامعتبر باشد یا شبکه‌ی شما آن را مسدود کند.';

                setState(button, 'ping-fail', result.reason === 'timeout' ? 'بی‌پاسخ' : 'خطا');
                button.title = reason + '\n' + target.href;
                return;
            }

            var ms = Math.round(result.ms);
            var level = levelFor(ms);

            setState(button, 'ping-' + level.key, faNum(ms) + ' ms');
            button.title = successTitle(target, ms, level, result.status);
        });
    }

    document.addEventListener('click', function (event) {
        var node = event.target;
        var button = node && node.closest ? node.closest('.ping-btn') : null;
        if (!button) return;

        event.preventDefault();
        event.stopPropagation();
        runPing(button);
    }, true);
})();
