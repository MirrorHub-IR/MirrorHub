<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/layout.php';
require_once '../includes/monitoring.php';

requireAdmin();

$lastRunResult = null;

/* ============================================================
   عملیات (POST)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        flash('error', 'توکن امنیتی نامعتبر است.');
        redirect(SITE_URL . '/admin/monitor.php');
    }

    $reqAction = $_POST['req_action'] ?? '';

    if ($reqAction === 'test_bale') {
        $savedSettings = monitorSettings($pdo);
        if (empty($savedSettings['bale_bot_token']) || empty($savedSettings['bale_chat_id'])) {
            flash('error', 'ابتدا توکن ربات بله و شناسهٔ مقصد را ذخیره کنید.');
        } elseif (monitorNotify($savedSettings, '✅ پیام آزمایشی MirrorHub: اتصال هشدارهای پایش به بله برقرار است.')) {
            flash('success', 'پیام آزمایشی ارسال شد؛ گفت‌وگوی مقصد را بررسی کنید.');
        } else {
            flash('error', 'ارسال پیام آزمایشی ناموفق بود. توکن، شناسهٔ مقصد، دسترسی ربات و اتصال HTTPS را بررسی کنید.');
        }
        redirect(SITE_URL . '/admin/monitor.php');
    }

    if ($reqAction === 'run_now') {
        if (function_exists('set_time_limit')) @set_time_limit(300);
        $lastRunResult = monitorRunCycle($pdo, ['force' => true]);
        if (!empty($lastRunResult['skipped'])) {
            flash('error', 'چرخه اجرا نشد: ' . $lastRunResult['reason']);
        } else {
            $message = 'چرخهٔ HTTP اجرا شد: ' . monitorFa($lastRunResult['checked']) . ' میرور (آنلاین: '
                . monitorFa($lastRunResult['up']) . '، کند: ' . monitorFa($lastRunResult['slow']) . '، قطع: '
                . monitorFa($lastRunResult['down']) . ').';
            if (!empty($lastRunResult['repository_checks'])) {
                $message .= ' بررسی محتوای مخزن: ' . monitorFa($lastRunResult['repository_checks']) . ' (سالم: '
                    . monitorFa($lastRunResult['repository_healthy']) . '، قدیمی: '
                    . monitorFa($lastRunResult['repository_stale']) . '، نامعتبر: '
                    . monitorFa($lastRunResult['repository_invalid']) . '، خطا: '
                    . monitorFa($lastRunResult['repository_error']) . ').';
            }
            flash('success', $message);
        }
        redirect(SITE_URL . '/admin/monitor.php');
    }

    if ($reqAction === 'save_settings') {
        $enabled = isset($_POST['enabled']) ? '1' : '0';
        $timeout = (int)($_POST['timeout_seconds'] ?? 8);
        $slowMs = (int)($_POST['slow_threshold_ms'] ?? 1200);
        $interval = (int)($_POST['interval_hours'] ?? 1);
        $batchLimit = (int)($_POST['batch_limit'] ?? 200);
        $failureThreshold = (int)($_POST['failure_threshold'] ?? 2);
        $retention = (int)($_POST['retention_days'] ?? 180);
        $alertDown = isset($_POST['alert_down']) ? '1' : '0';
        $alertRecovery = isset($_POST['alert_recovery']) ? '1' : '0';
        $baleToken = trim((string)($_POST['bale_bot_token'] ?? ''));
        $baleChat = trim((string)($_POST['bale_chat_id'] ?? ''));
        $webhook = trim((string)($_POST['webhook_url'] ?? ''));

        $errors = [];
        if ($timeout < 2 || $timeout > 30) $errors[] = 'مهلت پاسخ باید بین ۲ تا ۳۰ ثانیه باشد.';
        if ($slowMs < 100 || $slowMs > 30000) $errors[] = 'آستانهٔ کندی باید بین ۱۰۰ تا ۳۰۰۰۰ میلی‌ثانیه باشد.';
        if ($interval < 1 || $interval > 168) $errors[] = 'بازهٔ پایش باید بین ۱ تا ۱۶۸ ساعت باشد.';
        if ($batchLimit < 1 || $batchLimit > 1000) $errors[] = 'تعداد هدف در هر چرخه باید بین ۱ تا ۱۰۰۰ باشد.';
        if ($failureThreshold < 1 || $failureThreshold > 5) $errors[] = 'آستانهٔ خطای متوالی باید بین ۱ تا ۵ باشد.';
        if ($retention < 7 || $retention > 730) $errors[] = 'مدت نگهداری تاریخچه باید بین ۷ تا ۷۳۰ روز باشد.';
        if ($webhook !== '' && !filter_var($webhook, FILTER_VALIDATE_URL)) $errors[] = 'آدرس وب‌هوک معتبر نیست.';
        if ($baleToken !== '' && $baleChat === '') $errors[] = 'برای استفاده از بله، شناسهٔ مقصد هم لازم است.';

        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect(SITE_URL . '/admin/monitor.php');
        }

        monitorSaveSettings($pdo, [
            'enabled'            => $enabled,
            'timeout_seconds'    => (string)$timeout,
            'slow_threshold_ms'  => (string)$slowMs,
            'interval_hours'     => (string)$interval,
            'batch_limit'         => (string)$batchLimit,
            'failure_threshold'   => (string)$failureThreshold,
            'retention_days'     => (string)$retention,
            'alert_down'         => $alertDown,
            'alert_recovery'     => $alertRecovery,
            'bale_bot_token'     => $baleToken,
            'bale_chat_id'       => $baleChat,
            'webhook_url'        => $webhook,
        ]);
        flash('success', 'تنظیمات پایش ذخیره شد.');
        redirect(SITE_URL . '/admin/monitor.php');
    }

    if ($reqAction === 'purge_history') {
        $target = (int)($_POST['mirror_id'] ?? 0) ?: null;
        monitorPurgeHistory($pdo, $target);
        flash('success', $target ? 'تاریخچهٔ این میرور پاک شد.' : 'تاریخچهٔ همهٔ میرورها پاک شد.');
        redirect(SITE_URL . '/admin/monitor.php');
    }

    flash('error', 'عملیات ناشناخته است.');
    redirect(SITE_URL . '/admin/monitor.php');
}

/* ============================================================
   داده‌ها برای نمایش
   ============================================================ */
ensureMonitorSchema($pdo);
ensureCategoryBaseUrlSchema($pdo);
$settings = monitorSettings($pdo);
$summary = monitorStatusSummary($pdo);

$providerOptions = [];
try {
    $providerOptions = $pdo->query("SELECT id, name, base_url FROM categories
        WHERE type = 'provider' AND base_url IS NOT NULL AND TRIM(base_url) <> ''
        ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $providerOptions = array_values(array_filter($providerOptions, static function ($provider): bool {
        return trim((string)($provider['base_url'] ?? '')) !== ''
            && categoryBaseUrlIsValid((string)$provider['base_url']);
    }));
} catch (Throwable $e) {
    $providerOptions = [];
}
$providerInput = $_GET['provider_id'] ?? '';
$providerFilterId = is_scalar($providerInput) && ctype_digit((string)$providerInput)
    ? (int)$providerInput
    : 0;
$selectedProvider = null;
if ($providerFilterId > 0) {
    foreach ($providerOptions as $providerOption) {
        if ((int)$providerOption['id'] === $providerFilterId) {
            $selectedProvider = $providerOption;
            break;
        }
    }
}
if (!$selectedProvider) $providerFilterId = 0;

$rows = monitorMirrorRows($pdo);
$incidents = [];
if ($selectedProvider) {
    $providerMirrorIds = [];
    $providerMirrorQuery = $pdo->prepare("SELECT mirror_id FROM mirror_categories WHERE category_id = ?");
    $providerMirrorQuery->execute([(int)$selectedProvider['id']]);
    foreach ($providerMirrorQuery->fetchAll(PDO::FETCH_COLUMN) as $providerMirrorId) {
        $providerMirrorIds[(int)$providerMirrorId] = true;
    }
    $rows = array_values(array_filter($rows, static function ($row) use ($providerMirrorIds, $selectedProvider): bool {
        return isset($providerMirrorIds[(int)$row['id']])
            && providerUrlMatchesBaseUrl((string)$row['url'], (string)$selectedProvider['base_url']);
    }));

    $summary['mirrors'] = count($rows);
    $summary['up'] = 0;
    $summary['slow'] = 0;
    $summary['down'] = 0;
    $summary['unknown'] = 0;
    foreach ($rows as $scopedRow) {
        $state = in_array($scopedRow['status'] ?? '', ['up', 'slow', 'down'], true)
            ? (string)$scopedRow['status']
            : 'unknown';
        $summary[$state]++;
    }
    $scopeIds = array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'id')), static fn($id) => $id > 0)));
    $summary['avg_response_ms'] = null;
    $summary['uptime_percent'] = null;
    $summary['total_checks'] = 0;
    if ($scopeIds) {
        $scopeIdSql = implode(',', $scopeIds);
        $scopeAggregate = $pdo->query("SELECT COUNT(*) AS total,
                SUM(status IN ('up','slow')) AS ok,
                AVG(CASE WHEN status IN ('up','slow') AND response_ms IS NOT NULL THEN response_ms END) AS avg_ms
            FROM mirror_checks
            WHERE checked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND mirror_id IN ($scopeIdSql)")->fetch(PDO::FETCH_ASSOC);
        if ($scopeAggregate && (int)$scopeAggregate['total'] > 0) {
            $summary['uptime_percent'] = round((int)$scopeAggregate['ok'] * 100 / (int)$scopeAggregate['total'], 2);
            $summary['avg_response_ms'] = $scopeAggregate['avg_ms'] !== null
                ? (int)round((float)$scopeAggregate['avg_ms'])
                : null;
            $summary['total_checks'] = (int)$scopeAggregate['total'];
        }
        $incidentQuery = $pdo->query("SELECT i.*, m.name_fa, m.name_en, m.url
            FROM mirror_incidents i
            INNER JOIN mirrors m ON m.id = i.mirror_id
            WHERE i.mirror_id IN ($scopeIdSql)
            ORDER BY i.started_at DESC LIMIT 10");
        $incidents = $incidentQuery->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    $incidents = monitorRecentIncidents($pdo, 10);
}

$schemeFilter = strtolower(trim((string)($_GET['scheme'] ?? 'all')));
if (!in_array($schemeFilter, ['all', 'http', 'https'], true)) $schemeFilter = 'all';
$schemeCounts = ['http' => 0, 'https' => 0];
foreach ($rows as $monitorRow) {
    $rowScheme = monitorUrlScheme((string)($monitorRow['url'] ?? ''));
    if ($rowScheme !== '') $schemeCounts[$rowScheme]++;
}
if ($schemeFilter !== 'all') {
    $rows = array_values(array_filter($rows, static fn($row) => monitorUrlScheme((string)($row['url'] ?? '')) === $schemeFilter));
}
$cronKey = monitorCronKey($pdo);
$intervalHours = monitorConfiguredIntervalHours($settings);
$cronExpression = monitorCronExpression($intervalHours);
$windowsSchedule = $intervalHours < 24 ? 'HOURLY' : 'DAILY';
$windowsModifier = $intervalHours < 24 ? $intervalHours : max(1, (int)($intervalHours / 24));
$cronUrl = SITE_URL . '/cron.php?key=' . ($cronKey !== '' ? htmlspecialchars($cronKey) : 'YOUR_KEY');
$cronTestUrl = $cronUrl . '&source=manual';
$scheme = parse_url(SITE_URL, PHP_URL_SCHEME) ?: 'https';

renderHeader('پایش سرورها', true);
?>

<div class="admin-monitor-page">

<section class="section admin-monitor-hero">
    <div class="section-header">
        <h1 class="section-title">پایش سرورها</h1>
        <div class="editor-actions">
            <form method="POST" action="<?= SITE_URL ?>/admin/monitor.php" class="inline-form">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="req_action" value="run_now">
                <button type="submit" class="btn btn-sm btn-primary"><?= icon('activity', 14) ?> اجرای دستی چرخه</button>
            </form>
            <a href="<?= SITE_URL ?>/status.php" class="btn btn-sm btn-outline" target="_blank">صفحهٔ عمومی وضعیت</a>
        </div>
    </div>
    <p class="section-desc">
        فقط نشانی‌های واقعی HTTP و HTTPS پایش می‌شوند و میرور باید به یک دستهٔ «ارائه‌دهنده» متصل باشد. مقدار پروتکل ثبت‌شده در فرم میرور صرفاً متادیتاست و مبنای این فیلتر نیست.
        بازهٔ فعلی اجرای واقعی پایش: <strong><?= htmlspecialchars(monitorIntervalLabel($intervalHours)) ?></strong>.
        همین مقدار در صفحهٔ عمومی وضعیت نمایش داده می‌شود و اجرای زمان‌بندی‌شدهٔ زودتر از موعد را متوقف می‌کند.
        <?php if (!empty($summary['observed_cron_interval_seconds'])): ?>
            فاصلهٔ مشاهده‌شده بین دو فراخوانی اخیر Cron: <strong><?= monitorDurationFa((int)$summary['observed_cron_interval_seconds']) ?></strong>.
        <?php elseif (!empty($summary['last_cron_run_at'])): ?>
            آخرین فراخوانی Cron: <strong><?= monitorTimeAgoFa($summary['last_cron_run_at']) ?></strong>؛ برای تخمین فاصله به اجرای بعدی نیاز است.
        <?php else: ?>
            هنوز اجرای زمان‌بند ثبت نشده است.
        <?php endif; ?>
    </p>

    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-icon mon-icon-up"><?= icon('check', 20) ?></span>
            <h3><?= monitorFa($summary['up']) ?></h3>
            <p>آنلاین</p>
        </div>
        <div class="stat-card">
            <span class="stat-icon mon-icon-slow"><?= icon('activity', 20) ?></span>
            <h3><?= monitorFa($summary['slow']) ?></h3>
            <p>کند</p>
        </div>
        <div class="stat-card">
            <span class="stat-icon mon-icon-down"><?= icon('x', 20) ?></span>
            <h3><?= monitorFa($summary['down']) ?></h3>
            <p>قطعی</p>
        </div>
        <div class="stat-card info">
            <span class="stat-icon"><?= icon('server', 20) ?></span>
            <h3><?= monitorFa($summary['mirrors']) ?></h3>
            <p>میرور تحت پایش</p>
        </div>
        <div class="stat-card">
            <span class="stat-icon"><?= icon('activity', 20) ?></span>
            <h3><?= $summary['avg_response_ms'] !== null ? monitorFa($summary['avg_response_ms']) . ' ms' : '—' ?></h3>
            <p>میانگین پاسخ (۳۰ روز)</p>
        </div>
        <div class="stat-card highlight">
            <span class="stat-icon"><?= icon('heart', 20) ?></span>
            <h3><?= $summary['uptime_percent'] !== null ? monitorFa(number_format($summary['uptime_percent'], 2)) . '٪' : '—' ?></h3>
            <p>آپتایم کل (۳۰ روز)</p>
            <span class="stat-sub">آخرین اجرا: <?= monitorTimeAgoFa($summary['last_run_at']) ?></span>
        </div>
    </div>
</section>

<section class="section admin-monitor-table-section">
    <div class="section-header">
        <h2 class="section-title">وضعیت میرورها</h2>
        <span class="badge badge-default"><?= monitorFa(count($rows)) ?> مورد در این فیلتر</span>
    </div>

    <form method="get" action="<?= SITE_URL ?>/admin/monitor.php" class="mon-filter-bar">
        <div>
            <strong>پالایش ارائه‌دهنده و پروتکل URL</strong>
            <small>فقط دسته‌های ارائه‌دهنده با Base URL معتبر HTTP/HTTPS قابل انتخاب‌اند؛ تطبیق مسیر پایه نیز بررسی می‌شود.</small>
        </div>
        <div class="mon-filter-field">
            <label for="monitor-provider-filter">ارائه‌دهنده</label>
            <select id="monitor-provider-filter" name="provider_id">
                <option value="0">همهٔ ارائه‌دهندگان</option>
                <?php foreach ($providerOptions as $providerOption): ?>
                    <option value="<?= (int)$providerOption['id'] ?>" <?= $providerFilterId === (int)$providerOption['id'] ? 'selected' : '' ?>><?= htmlspecialchars($providerOption['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mon-filter-field">
            <label for="monitor-scheme-filter">پروتکل نشانی</label>
            <select id="monitor-scheme-filter" name="scheme">
                <option value="all" <?= $schemeFilter === 'all' ? 'selected' : '' ?>>همهٔ HTTP/HTTPS (<?= monitorFa($schemeCounts['http'] + $schemeCounts['https']) ?>)</option>
                <option value="https" <?= $schemeFilter === 'https' ? 'selected' : '' ?>>HTTPS (<?= monitorFa($schemeCounts['https']) ?>)</option>
                <option value="http" <?= $schemeFilter === 'http' ? 'selected' : '' ?>>HTTP (<?= monitorFa($schemeCounts['http']) ?>)</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><?= icon('filter', 15) ?> اعمال فیلتر</button>
        <?php if ($schemeFilter !== 'all' || $selectedProvider): ?><a href="<?= SITE_URL ?>/admin/monitor.php" class="btn btn-outline">پاک‌کردن فیلتر</a><?php endif; ?>
    </form>

    <?php if (empty($rows)): ?>
        <p class="empty-state"><?php if ($selectedProvider): ?>برای ارائه‌دهندهٔ انتخاب‌شده، میرور فعال HTTP/HTTPS منطبق با Base URL پیدا نشد.<?php elseif ($schemeFilter !== 'all'): ?>میروری با این پروتکل در فهرست پایش پیدا نشد.<?php else: ?>میرور HTTP یا HTTPS واجد شرایطی برای پایش وجود ندارد.<?php endif; ?></p>
    <?php else: ?>
        <div class="mon-card-grid">
            <?php foreach ($rows as $row): ?>
                <?php
                    $names = mirrorDisplayName($row);
                    $daily = monitorUptimeSeries($pdo, (int)$row['id'], 30);
                    $latency = monitorLatencySeries($pdo, (int)$row['id'], 30);
                    $rowScheme = monitorUrlScheme((string)$row['url']);
                ?>
                <article class="mon-mirror-card mon-card-<?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') ?>">
                    <header class="mon-card-head">
                        <div class="mon-card-name">
                            <strong><?= htmlspecialchars($names['fa'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if (!empty($names['en']) && $names['en'] !== $names['fa']): ?><small><?= htmlspecialchars($names['en'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                            <a class="mon-url" href="<?= htmlspecialchars($row['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($row['url'], ENT_QUOTES, 'UTF-8') ?></a>
                        </div>
                        <span class="mon-scheme-tag mon-scheme-<?= $rowScheme ?>"><?= strtoupper($rowScheme) ?></span>
                    </header>
                    <div class="mon-card-badges">
                        <?= monitorStatusBadge($row) ?>
                        <?= monitorRepositoryHealthBadge($row) ?>
                    </div>
                    <?php if (!empty($row['repository_health_checked_at'])): ?>
                        <p class="mon-card-health-time">آخرین بررسی سلامت محتوا: <?= monitorTimeAgoFa($row['repository_health_checked_at']) ?></p>
                    <?php endif; ?>
                    <div class="mon-card-metrics">
                        <div><span>زمان پاسخ</span><strong><?= $row['last_response_ms'] !== null ? monitorFa((int)$row['last_response_ms']) . ' ms' : '—' ?></strong></div>
                        <div><span>کد HTTP</span><strong><?= $row['last_http_code'] ? monitorFa((int)$row['last_http_code']) : '—' ?></strong></div>
                        <div><span>آپتایم ۳۰ روز</span><strong><?= $row['uptime_percent'] !== null ? monitorFa(number_format((float)$row['uptime_percent'], 1)) . '٪' : '—' ?></strong></div>
                        <div><span>آخرین بررسی</span><strong><?= monitorTimeAgoFa($row['last_checked_at']) ?></strong></div>
                    </div>
                    <div class="mon-card-chart">
                        <div><span>پایداری روزانه</span><?= monitorUptimeBar($daily, 30) ?></div>
                        <div><span>روند پاسخ</span><?= $latency ? monitorSparkline($latency) : '<span class="mon-no-data">داده‌ای نیست</span>' ?></div>
                    </div>
                    <?php if ($row['status'] === 'down' && !empty($row['last_error'])): ?>
                        <div class="mon-card-error">
                            <?= icon('alert-triangle', 14) ?>
                            <span><?= htmlspecialchars($row['last_error'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if (!empty($row['down_since'])): ?><small>قطعی از <?= monitorFaDateTime($row['down_since']) ?></small><?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <footer class="mon-card-actions">
                        <span>شناسهٔ میرور: <?= monitorFa((int)$row['id']) ?></span>
                        <form method="POST" action="<?= SITE_URL ?>/admin/monitor.php" class="inline-form">
                            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="req_action" value="purge_history">
                            <input type="hidden" name="mirror_id" value="<?= (int)$row['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline btn-danger" onclick="return confirm('تاریخچهٔ پایش این میرور پاک شود؟')">پاک‌سازی تاریخچه</button>
                        </form>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section admin-monitor-schedule-section">
    <h2 class="section-title">زمان‌بندی اجرای خودکار</h2>
    <div class="mon-info-box">
        <p>
            چرخهٔ پایش با ۳ روش قابل زمان‌بندی است (توضیح کامل در <span class="ltr-text">docs/monitoring.md</span>):
        </p>
        <ol class="mon-cron-list">
            <li>
                <strong>cron سرور (مطابق بازهٔ انتخاب‌شده):</strong>
                <code class="ltr-text"><?= htmlspecialchars($cronExpression) ?> php <?= htmlspecialchars(rtrim(str_replace('\\', '/', dirname(__DIR__)), '/')) ?>/cron.php >> <?= htmlspecialchars(rtrim(str_replace('\\', '/', dirname(__DIR__)), '/')) ?>/cron/monitor.log 2>&1</code>
            </li>
            <li>
                <strong>وظیفهٔ زمان‌بندی‌شدهٔ ویندوز (مطابق بازهٔ انتخاب‌شده):</strong>
                <code class="ltr-text">schtasks /Create /TN "MirrorHub-Monitor" /SC <?= htmlspecialchars($windowsSchedule) ?> /MO <?= (int)$windowsModifier ?> /ST 04:15 /TR "\"C:\wamp64\bin\php\php8.2.26\php.exe\" \"<?= htmlspecialchars(dirname(__DIR__) . '\cron.php') ?>\""</code>
            </li>
            <li>
                <strong>سرویس‌های web-cron:</strong> در سرویس زمان‌بندی بیرونی نیز بازهٔ <strong><?= htmlspecialchars(monitorIntervalLabel($intervalHours)) ?></strong> را انتخاب کن. برنامه بازهٔ تنظیم‌شده را رعایت می‌کند:
                <code class="ltr-text" dir="ltr"><?= $cronUrl ?></code>
                <a href="<?= htmlspecialchars($cronTestUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline" target="_blank" rel="noopener noreferrer">تست این آدرس</a>
                <span class="form-hint">برای اجرای زمان‌بندی‌شده از همین آدرس استفاده کن و بازهٔ <?= htmlspecialchars(monitorIntervalLabel($intervalHours)) ?> را در سرویس ثبت کن. لینک آزمایش، یک اجرای دستی انجام می‌دهد.</span>
            </li>
        </ol>
        <p class="mon-hint-line">
            اگر Cron سرور یا web-cron فعال نیست، سایت فقط هنگام بازدید کاربران اجرای جایگزین را امتحان می‌کند؛ این روش برای محیط تولید قابل‌اتکا نیست.
            اگر سرویس Cron بیشتر از این بازه فراخوانی شود، برنامه چرخهٔ واقعی پایش را تا رسیدن موعد بعدی اجرا نمی‌کند. اجرای دستی پنل از این محدودیت مستثنا است.
        </p>
    </div>
</section>

<section class="section admin-monitor-settings-section">
    <h2 class="section-title">تنظیمات پالایش و پایش</h2>
    <div class="mon-info-box">
        <h3>راهنمای سریع</h3>
        <p>زمان انتظار تعیین می‌کند چقدر منتظر پاسخ بمانیم؛ آستانهٔ کندی سرعت پایین را مشخص می‌کند؛ خطای متوالی کمک می‌کند یک قطعی لحظه‌ای به‌اشتباه مشکل دائمی تشخیص داده نشود. مقدارهای پیش‌فرض برای شروع مناسب‌اند.</p>
    </div>
    <div class="form-container">
        <form method="POST" action="<?= SITE_URL ?>/admin/monitor.php">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="req_action" value="save_settings">

            <div class="form-group">
                <label class="mon-check-label">
                    <input type="checkbox" name="enabled" value="1" <?= $settings['enabled'] === '1' ? 'checked' : '' ?>>
                    پایش خودکار فعال باشد
                </label>
                <span class="form-hint">در صورت غیرفعال بودن، فقط اجرای دستی کار می‌کند و هشداری ارسال نمی‌شود.</span>
            </div>

            <div class="editor-grid">
                <div class="form-group">
                    <label for="timeout_seconds">چند ثانیه برای پاسخ هر میرور صبر کنیم؟</label>
                    <input type="number" id="timeout_seconds" name="timeout_seconds" min="2" max="30" value="<?= htmlspecialchars($settings['timeout_seconds']) ?>">
                    <span class="form-hint">اگر لینک در این زمان پاسخ ندهد، یک بار دیگر امتحان می‌شود؛ بین ۲ تا ۳۰ ثانیه.</span>
                </div>
                <div class="form-group">
                    <label for="slow_threshold_ms">از چه زمانی پاسخ «کند» حساب شود؟ (میلی‌ثانیه)</label>
                    <input type="number" id="slow_threshold_ms" name="slow_threshold_ms" min="100" max="30000" value="<?= htmlspecialchars($settings['slow_threshold_ms']) ?>">
                    <span class="form-hint">مثلاً ۱۲۰۰ یعنی ۱٫۲ ثانیه؛ اگر پاسخ بیشتر طول بکشد، میرور «کند» است ولی هنوز در دسترس محسوب می‌شود.</span>
                </div>
                <div class="form-group">
                    <label for="interval_hours">فاصلهٔ اجرای واقعی پایش</label>
                    <select id="interval_hours" name="interval_hours">
                        <?php foreach (monitorIntervalChoices() as $choiceHours): ?>
                            <option value="<?= (int)$choiceHours ?>" <?= $intervalHours === (int)$choiceHours ? 'selected' : '' ?>><?= htmlspecialchars(monitorIntervalLabel((int)$choiceHours)) ?></option>
                        <?php endforeach; ?>
                        <?php if (!in_array($intervalHours, monitorIntervalChoices(), true)): ?>
                            <option value="<?= (int)$intervalHours ?>" selected>مقدار فعلی قدیمی: هر <?= monitorFa($intervalHours) ?> ساعت</option>
                        <?php endif; ?>
                    </select>
                    <span class="form-hint">این یک تنظیم واحد برای چرخهٔ پایش، دستور پیشنهادی Cron و نمایش عمومی است. پس از تغییر بازه و ذخیره، دستور جدید را در cPanel جایگزین کن.</span>
                </div>
            </div>

            <details class="mon-advanced-settings" style="margin: 16px 0; padding: 16px; border: 1px solid var(--border-color); border-radius: var(--radius); background: var(--bg-secondary);">
                <summary style="cursor: pointer; font-weight: 600;">تنظیمات پیشرفته (تاریخچه، هشدارها و تعداد بررسی)</summary>
                <div class="form-group" style="margin-top: 16px;">
                    <label for="retention_days">مدت نگهداری تاریخچه (روز)</label>
                    <input type="number" id="retention_days" name="retention_days" min="7" max="730" value="<?= htmlspecialchars($settings['retention_days']) ?>">
                    <span class="form-hint">بررسی‌های قدیمی‌تر خودکار حذف می‌شوند.</span>
                </div>

                <div class="form-group">
                <label class="mon-check-label">
                    <input type="checkbox" name="alert_down" value="1" <?= $settings['alert_down'] === '1' ? 'checked' : '' ?>>
                    هشدار هنگام قطع شدن میرور ارسال شود
                </label>
                <label class="mon-check-label">
                    <input type="checkbox" name="alert_recovery" value="1" <?= $settings['alert_recovery'] === '1' ? 'checked' : '' ?>>
                    هشدار هنگام برگشت میرور ارسال شود
                </label>
            </div>

            <div class="editor-grid">
                <div class="form-group">
                    <label for="bale_bot_token">توکن ربات بله (اختیاری)</label>
                    <input type="password" id="bale_bot_token" name="bale_bot_token" dir="ltr" autocomplete="new-password" value="<?= htmlspecialchars($settings['bale_bot_token']) ?>" placeholder="توکن دریافتی از BotFather بله">
                    <span class="form-hint">توکن در سرور ذخیره می‌شود؛ آن را در مخزن کد یا گزارش‌ها قرار ندهید.</span>
                </div>
                <div class="form-group">
                    <label for="bale_chat_id">شناسهٔ مقصد در بله</label>
                    <input type="text" id="bale_chat_id" name="bale_chat_id" dir="ltr" value="<?= htmlspecialchars($settings['bale_chat_id']) ?>" placeholder="شناسهٔ گفت‌وگو یا کانال">
                </div>
            </div>
            <div class="editor-grid">
                <div class="form-group">
                    <label for="batch_limit">چند میرور در هر اجرا بررسی شود؟</label>
                    <input type="number" id="batch_limit" name="batch_limit" min="1" max="1000" value="<?= htmlspecialchars($settings['batch_limit']) ?>">
                    <span class="form-hint">برای شروع ۲۰۰ مناسب است. اگر میرورها بیشتر باشند، میرورهای بررسی‌نشده یا قدیمی‌تر در اجراهای بعدی نوبت می‌گیرند.</span>
                </div>
                <div class="form-group">
                    <label for="failure_threshold">چند چرخهٔ ناموفق تا اعلام قطعی؟</label>
                    <input type="number" id="failure_threshold" name="failure_threshold" min="1" max="5" value="<?= htmlspecialchars($settings['failure_threshold']) ?>">
                    <span class="form-hint">برای کم‌شدن هشدار اشتباه، پیشنهاد می‌شود روی ۲ بماند؛ هر بررسی هم در همان چرخه یک بار دوباره امتحان می‌شود.</span>
                </div>
            </div>

            <div class="form-group">
                <label for="webhook_url">وب‌هوک دلخواه (اختیاری)</label>
                <input type="url" id="webhook_url" name="webhook_url" dir="ltr" value="<?= htmlspecialchars($settings['webhook_url']) ?>" placeholder="https://hook.example.com/mirrorhub">
                <span class="form-hint">در صورت قطعی/برگشت میرور، یک درخواست POST با بدنهٔ JSON به این آدرس فرستاده می‌شود.</span>
            </div>

            <div class="form-group">
                <label>کلید دسترسی cron (برای web-cron)</label>
                <code class="ltr-text mon-key-box" dir="ltr"><?= htmlspecialchars($cronKey !== '' ? $cronKey : 'پس از ذخیرهٔ اولین تنظیمات ساخته می‌شود') ?></code>
                <span class="form-hint">این کلید را در اختیار سرویس web-cron بگذارید؛ برای امنیت در آدرس بالا قابل تعویض نیست ولی در صورت لو رفتن، با تعریف MONITOR_CRON_KEY در config.php جایگزین می‌شود.</span>
            </div>

            </details>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">ذخیرهٔ تنظیمات</button>
            </div>
        </form>
        <form method="POST" action="<?= SITE_URL ?>/admin/monitor.php" class="inline-form" style="margin-top:12px">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="req_action" value="test_bale">
            <button type="submit" class="btn btn-outline">ارسال پیام آزمایشی به بله</button>
            <span class="form-hint">ابتدا تنظیمات را ذخیره کنید؛ سپس پیام آزمایشی ارسال می‌شود.</span>
        </form>
    </div>
</section>

<?php if (!empty($incidents)): ?>
<section class="section">
    <h2 class="section-title">رخدادهای اخیر قطعی</h2>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>میرور</th>
                    <th>شروع</th>
                    <th>پایان</th>
                    <th>مدت</th>
                    <th>دلیل</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($incidents as $inc): ?>
                    <tr>
                        <td data-label="میرور"><?= htmlspecialchars(trim($inc['name_fa'] . ' ' . $inc['name_en'], ' ')) ?></td>
                        <td data-label="شروع"><?= monitorFaDateTime($inc['started_at']) ?></td>
                        <td data-label="پایان">
                            <?php if ($inc['ended_at']): ?>
                                <?= monitorFaDateTime($inc['ended_at']) ?>
                            <?php else: ?>
                                <span class="mon-badge mon-down"><span class="mon-dot"></span>ادامه دارد</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="مدت"><?= $inc['duration_s'] !== null ? monitorDurationFa((int)$inc['duration_s']) : '—' ?></td>
                        <td data-label="دلیل" class="mon-incident-note"><?= htmlspecialchars($inc['note'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="section-header">
        <h2 class="section-title">آخرین بررسی‌های ثبت‌شده</h2>
        <form method="POST" action="<?= SITE_URL ?>/admin/monitor.php" class="inline-form">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="req_action" value="purge_history">
            <button type="submit" class="btn btn-sm btn-outline btn-danger" onclick="return confirm('تاریخچهٔ پایش همهٔ میرورها پاک شود؟')">پاک‌سازی کل تاریخچه</button>
        </form>
    </div>
    <?php
        $recent = [];
        try {
            $recent = $pdo->query("SELECT c.*, m.name_fa, m.name_en FROM mirror_checks c
                INNER JOIN mirrors m ON m.id = c.mirror_id
                ORDER BY c.id DESC LIMIT 25")->fetchAll();
        } catch (Throwable $e) {
        }
    ?>
    <?php if (empty($recent)): ?>
        <p class="empty-state">هنوز بررسی‌ای ثبت نشده است؛ یک چرخه اجرا کنید.</p>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>زمان</th>
                        <th>میرور</th>
                        <th>نتیجه</th>
                        <th>زمان پاسخ</th>
                        <th>کد</th>
                        <th>روش</th>
                        <th>توضیح</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $check): ?>
                        <tr>
                            <td data-label="زمان"><?= monitorFaDateTime($check['checked_at']) ?></td>
                            <td data-label="میرور"><?= htmlspecialchars(trim($check['name_fa'] . ' ' . $check['name_en'], ' ')) ?></td>
                            <td data-label="نتیجه"><span class="mon-badge mon-sm mon-<?= htmlspecialchars($check['status']) ?>"><span class="mon-dot"></span><?= monitorStatusFa($check['status']) ?></span></td>
                            <td data-label="زمان پاسخ"><?= $check['response_ms'] !== null ? monitorFa((int)$check['response_ms']) . ' ms' : '—' ?></td>
                            <td data-label="کد"><?= $check['http_code'] ? monitorFa((int)$check['http_code']) : '—' ?></td>
                            <td data-label="روش" class="ltr-text"><?= htmlspecialchars($check['method']) ?></td>
                            <td data-label="توضیح" class="mon-incident-note"><?= htmlspecialchars($check['error'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

</div>

<?php renderFooter(); ?>
