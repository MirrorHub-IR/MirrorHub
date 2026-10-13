/* ============================================================
   MirrorHub — به‌روزرسانی خودکار صفحهٔ وضعیت
   ------------------------------------------------------------
   هر MH_MONITOR_REFRESH ثانیه، خلاصهٔ وضعیت از api.php گرفته
   می‌شود و بنر/کارت‌های صفحه بدون رفرش کامل به‌روز می‌شوند.
   فقط عدد و برچسب‌ها عوض می‌شوند؛ چیدمان جدول دست نمی‌خورد.
   ============================================================ */
(function () {
    'use strict';

    var interval = parseInt(window.MH_MONITOR_REFRESH, 10);
    if (!interval || interval < 15) return;

    var faNum = function (value) {
        try { return new Intl.NumberFormat('fa-IR').format(value); }
        catch (e) { return String(value); }
    };

    var setText = function (selector, text) {
        var el = document.querySelector(selector);
        if (el) el.textContent = text;
    };

    var refresh = function () {
        if (document.visibilityState === 'hidden') return;

        var url = (window.MH_SITE_URL || '') + '/api.php?action=monitor_summary';
        fetch(url, { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json || !json.success || !json.data) return;
                var d = json.data;
                var s = d.summary || {};

                setText('#mon-count-up', faNum(s.up || 0));
                setText('#mon-count-slow', faNum(s.slow || 0));
                setText('#mon-count-down', faNum(s.down || 0));
                setText('#mon-count-total', faNum(s.mirrors || 0));
                setText('#mon-avg-ms', s.avg_response_ms != null ? faNum(s.avg_response_ms) + ' ms' : '—');
                setText('#mon-uptime', s.uptime_percent != null
                    ? faNum(Number(s.uptime_percent).toFixed(2).replace('.', '٫')) + '٪' : '—');
                setText('#mon-last-run', s.last_run_ago || '—');

                var hero = document.querySelector('[data-mon-hero]');
                if (hero) {
                    var cls = 'mon-hero';
                    var label = 'هنوز بررسی خودکار انجام نشده است';
                    if ((s.down || 0) > 0) { cls += ' mon-hero-down'; label = 'اختلال در برخی میرورها'; }
                    else if ((s.slow || 0) > 0) { cls += ' mon-hero-slow'; label = 'کندی در برخی میرورها گزارش شده'; }
                    else if ((s.up || 0) > 0) { cls += ' mon-hero-up'; label = 'همه میرورها فعال هستند'; }
                    else { cls += ' mon-hero-unknown'; }
                    hero.className = cls;
                    setText('#mon-hero-label', label);
                }

                var stamp = document.querySelector('#mon-last-update');
                if (stamp) {
                    stamp.title = 'به‌روزرسانی: ' + new Date().toLocaleTimeString('fa-IR');
                }
            })
            .catch(function () { /* شبکه در دسترس نیست؛ در بازدید بعدی تلاش می‌شود */ });
    };

    setTimeout(function () {
        setInterval(refresh, interval * 1000);
    }, interval * 1000);
})();
