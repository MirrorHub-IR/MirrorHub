<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/layout.php';
require_once 'includes/monitoring.php';

ensureCategoryBaseUrlSchema($pdo);

/* Provider filter: only provider categories with a valid HTTP(S) Base URL are selectable. */
$providerOptions = [];
try {
    $providerOptions = $pdo->query("SELECT id, name, base_url FROM categories
        WHERE type = 'provider' AND base_url IS NOT NULL AND TRIM(base_url) <> ''
        ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $providerOptions = array_values(array_filter($providerOptions, static function ($provider): bool {
        return trim((string)($provider['base_url'] ?? '')) !== '' && categoryBaseUrlIsValid((string)$provider['base_url']);
    }));
} catch (Throwable $e) {
    $providerOptions = [];
}
$selectedProviderId = filter_var($_GET['provider_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$selectedProvider = null;
if ($selectedProviderId !== false && $selectedProviderId !== null) {
    foreach ($providerOptions as $providerOption) {
        if ((int)$providerOption['id'] === (int)$selectedProviderId) {
            $selectedProvider = $providerOption;
            break;
        }
    }
}
if (!$selectedProvider) $selectedProviderId = 0;

$summary = monitorStatusSummary($pdo);
$rows = monitorMirrorRows($pdo);
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

    // When a provider is selected, make the summary cards match the same
    // Base URL-scoped mirror set instead of showing the whole system totals.
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
    }
}
$providerFilteredTotal = count($rows);
if ($selectedProvider) {
    $scopeIdMap = array_fill_keys(array_map('intval', array_column($rows, 'id')), true);
    $incidents = array_values(array_filter(
        monitorRecentIncidents($pdo, 100),
        static fn($incident) => isset($scopeIdMap[(int)($incident['mirror_id'] ?? 0)])
    ));
    $incidents = array_slice($incidents, 0, 10);
} else {
    $incidents = monitorRecentIncidents($pdo, 10);
}
$mirrorLimit = 40;
$rows = array_slice($rows, 0, $mirrorLimit);
$monitoredIds = array_column($rows, 'id');
$uptimeSeriesByMirror = monitorUptimeSeriesMap($pdo, $monitoredIds, 30);
$latencySeriesByMirror = monitorLatencySeriesMap($pdo, $monitoredIds, 30);

/* وضعیت کلی برای بنر بالای صفحه */
if ($summary['down'] > 0) {
    $overall = ['class' => 'down', 'label' => 'اختلال در برخی میرورها', 'icon' => 'alert-triangle'];
} elseif ($summary['slow'] > 0) {
    $overall = ['class' => 'slow', 'label' => 'کندی در برخی میرورها گزارش شده', 'icon' => 'alert-triangle'];
} elseif ($summary['up'] > 0) {
    $overall = ['class' => 'up', 'label' => 'همه میرورها فعال هستند', 'icon' => 'check'];
} else {
    $overall = ['class' => 'unknown', 'label' => 'هنوز بررسی خودکار انجام نشده است', 'icon' => 'info'];
}

$GLOBALS['pageSeo'] = [
    'title' => 'وضعیت میرورها | پایش و دسترس‌پذیری در MirrorHub',
    'description' => 'وضعیت ثبت‌شدهٔ میرورهای نرم‌افزاری، زمان پاسخ، آپتایم ۳۰ روزه و رخدادهای اخیر را در داشبورد MirrorHub بررسی کنید.',
    'canonical' => rtrim(SITE_URL, '/') . '/status/',
];
renderHeader('وضعیت سرورها');
?>

<section class="section">
    <div class="section-header">
        <h1 class="section-title">وضعیت لحظه‌ای سرورها</h1>
        <span class="badge badge-primary" id="mon-last-update" title="آخرین به‌روزرسانی خودکار صفحه">
            <?= icon('activity', 13) ?> <?= $selectedProvider ? 'نمایش آمار ارائه‌دهندهٔ انتخابی' : 'به‌روزرسانی خودکار هر ' . monitorFa(60) . ' ثانیه' ?>
        </span>
    </div>

    <!-- بنر وضعیت کلی -->
    <div class="mon-hero mon-hero-<?= $overall['class'] ?>" id="mon-hero" data-mon-hero>
        <span class="mon-hero-dot"></span>
        <div class="mon-hero-copy">
            <h2 id="mon-hero-label"><?= htmlspecialchars($overall['label']) ?></h2>
            <p>
                آخرین چرخهٔ پایش:
                <strong id="mon-last-run"><?= monitorTimeAgoFa($summary['last_run_at']) ?></strong>
                <?php if ($summary['last_run_checked'] > 0): ?>
                    — <?php if ($selectedProvider): ?>در اجرای کلی سامانه <?= monitorFa($summary['last_run_checked']) ?> میرور بررسی شد<?php else: ?><?= monitorFa($summary['last_run_checked']) ?> میرور بررسی شد<?php endif; ?>
                <?php endif; ?>
                <?php if ($summary['enabled']): ?>
                    — بازهٔ پایش خودکار: <strong><?= htmlspecialchars(monitorIntervalLabel(monitorConfiguredIntervalHours($summary))) ?></strong>
                    <?php if (!empty($summary['last_cron_run_at'])): ?>
                        — آخرین فراخوانی زمان‌بند: <?= monitorTimeAgoFa($summary['last_cron_run_at']) ?>
                    <?php endif; ?>
                <?php else: ?>
                    — <strong class="mon-warn-text">پایش خودکار در تنظیمات غیرفعال است</strong>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- کارت‌های آمار -->
    <div class="stats-grid" id="mon-stats">
        <div class="stat-card">
            <span class="stat-icon mon-icon-up"><?= icon('check', 20) ?></span>
            <h3 id="mon-count-up"><?= monitorFa($summary['up']) ?></h3>
            <p>آنلاین</p>
        </div>
        <div class="stat-card">
            <span class="stat-icon mon-icon-slow"><?= icon('activity', 20) ?></span>
            <h3 id="mon-count-slow"><?= monitorFa($summary['slow']) ?></h3>
            <p>کند</p>
        </div>
        <div class="stat-card">
            <span class="stat-icon mon-icon-down"><?= icon('x', 20) ?></span>
            <h3 id="mon-count-down"><?= monitorFa($summary['down']) ?></h3>
            <p>قطعی</p>
        </div>
        <div class="stat-card info">
            <span class="stat-icon"><?= icon('server', 20) ?></span>
            <h3 id="mon-count-total"><?= monitorFa($summary['mirrors']) ?></h3>
            <p><?= $selectedProvider ? 'میرور منطبق با ارائه‌دهنده' : 'میرور تحت پایش' ?></p>
        </div>
        <div class="stat-card">
            <span class="stat-icon"><?= icon('activity', 20) ?></span>
            <h3 id="mon-avg-ms"><?= $summary['avg_response_ms'] !== null ? monitorFa($summary['avg_response_ms']) . ' ms' : '—' ?></h3>
            <p>میانگین زمان پاسخ (۳۰ روز)</p>
        </div>
        <div class="stat-card highlight">
            <span class="stat-icon"><?= icon('heart', 20) ?></span>
            <h3 id="mon-uptime"><?= $summary['uptime_percent'] !== null ? monitorFa(number_format($summary['uptime_percent'], 2)) . '٪' : '—' ?></h3>
            <p>آپتایم کل (۳۰ روز)</p>
        </div>
    </div>
</section>

<section class="section mon-status-results">
    <div class="mon-status-filter-panel">
        <form method="get" action="<?= SITE_URL ?>/status.php" class="mon-status-filter">
            <div class="mon-status-filter-copy">
                <strong>پالایش بر اساس ارائه‌دهنده</strong>
                <small>فقط دسته‌های «ارائه‌دهنده» با Base URL معتبر HTTP/HTTPS در این فهرست هستند. میرورها باید به همان دسته متصل باشند و URL آن‌ها با scheme، میزبان، پورت و مسیر پایه مطابقت داشته باشد.</small>
            </div>
            <div class="mon-status-filter-field">
                <label for="status-provider-filter">ارائه‌دهنده</label>
                <select id="status-provider-filter" name="provider_id">
                    <option value="0">همهٔ ارائه‌دهندگان</option>
                    <?php foreach ($providerOptions as $providerOption): ?>
                        <option value="<?= (int)$providerOption['id'] ?>" <?= (int)$selectedProviderId === (int)$providerOption['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($providerOption['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">اعمال فیلتر</button>
            <?php if ($selectedProvider): ?><a class="btn btn-outline" href="<?= SITE_URL ?>/status.php">پاک‌کردن فیلتر</a><?php endif; ?>
        </form>
        <?php if (!$providerOptions): ?>
            <p class="mon-status-filter-help">هنوز دستهٔ ارائه‌دهنده‌ای با Base URL معتبر ثبت نشده است؛ برای فعال‌شدن پالایش، در پنل مدیریت ← دسته‌بندی‌ها، برای یک دستهٔ ارائه‌دهنده نشانی پایهٔ HTTP یا HTTPS ثبت کنید.</p>
        <?php elseif ($selectedProvider): ?>
            <p class="mon-status-filter-help">فیلتر فعال: <strong><?= htmlspecialchars($selectedProvider['name'], ENT_QUOTES, 'UTF-8') ?></strong> — <?= monitorFa($providerFilteredTotal) ?> میرور منطبق با Base URL</p>
        <?php else: ?>
            <p class="mon-status-filter-help">فهرست وضعیت شامل <?= monitorFa($providerFilteredTotal) ?> میرور HTTP/HTTPS متصل به دسته‌های ارائه‌دهنده است؛ در هر صفحه حداکثر <?= monitorFa($mirrorLimit) ?> مورد نمایش داده می‌شود.</p>
        <?php endif; ?>
    </div>

    <div class="section-header">
        <h2 class="section-title">جزئیات میرورها</h2>
        <span class="badge badge-default"><?= monitorFa(min($providerFilteredTotal, $mirrorLimit)) ?> از <?= monitorFa($providerFilteredTotal) ?> مورد</span>
    </div>
    <?php if (empty($rows)): ?>
        <p class="empty-state"><?= $selectedProvider ? 'برای این ارائه‌دهنده، میرور فعال HTTP/HTTPS منطبق با Base URL پیدا نشد.' : 'هنوز میروری برای پایش ثبت نشده است.' ?></p>
    <?php else: ?>
        <div class="table-container mon-table-wrap">
            <table class="data-table mon-table">
                <thead>
                    <tr>
                        <th>میرور</th>
                        <th>وضعیت HTTP</th>
                        <th>سلامت مخزن</th>
                        <th>زمان پاسخ</th>
                        <th>آپتایم ۳۰ روز</th>
                        <th>روند ۳۰ بررسی اخیر</th>
                        <th>آخرین بررسی</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                            $names = mirrorDisplayName($row);
                            $rowId = (int)$row['id'];
                            $daily = $uptimeSeriesByMirror[$rowId] ?? [];
                            $latency = $latencySeriesByMirror[$rowId] ?? [];
                        ?>
                        <tr class="mon-row mon-row-<?= htmlspecialchars($row['status']) ?>">
                            <td data-label="میرور">
                                <div class="mon-mirror-name">
                                    <strong><?= htmlspecialchars($names['fa']) ?></strong>
                                    <?php if ($names['en'] && $names['en'] !== $names['fa']): ?>
                                        <span class="ltr-text mon-name-en"><?= htmlspecialchars($names['en']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <a class="ltr-text mon-url" href="<?= htmlspecialchars($row['url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars(excerpt($row['url'], 45)) ?></a>
                                <?php if ($row['status'] === 'down' && !empty($row['down_since'])): ?>
                                    <div class="mon-since">قطعی از <?= monitorFaDateTime($row['down_since']) ?> (<?= monitorTimeAgoFa($row['down_since']) ?>)</div>
                                <?php endif; ?>
                            </td>
                            <td data-label="وضعیت HTTP" class="mon-cell-status"><?= monitorStatusBadge($row) ?></td>
                            <td data-label="سلامت مخزن">
                                <?= monitorRepositoryHealthBadge($row) ?>
                                <?php if (!empty($row['repository_health_checked_at'])): ?><small class="mon-last-check"><?= monitorTimeAgoFa($row['repository_health_checked_at']) ?></small><?php endif; ?>
                            </td>
                            <td data-label="زمان پاسخ">
                                <?php if ($row['last_response_ms'] !== null && in_array($row['status'], ['up', 'slow'], true)): ?>
                                    <span class="mon-ms-value mon-ms-<?= htmlspecialchars($row['status']) ?>"><?= monitorFa((int)$row['last_response_ms']) ?> ms</span>
                                <?php else: ?>
                                    <span class="mon-ms-value">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="آپتایم">
                                <div class="mon-uptime-num"><?= $row['uptime_percent'] !== null ? monitorFa(number_format((float)$row['uptime_percent'], 1)) . '٪' : '—' ?></div>
                                <?= monitorUptimeBar($daily, 30) ?>
                            </td>
                            <td data-label="روند">
                                <?= $latency ? monitorSparkline($latency) : '<span class="mon-no-data">داده‌ای نیست</span>' ?>
                            </td>
                            <td data-label="آخرین بررسی" class="mon-last-check"><?= monitorTimeAgoFa($row['last_checked_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if (!empty($incidents)): ?>
<section class="section">
    <h2 class="section-title">رخدادهای اخیر</h2>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>میرور</th>
                    <th>شروع قطعی</th>
                    <th>پایان</th>
                    <th>مدت</th>
                    <th>دلیل</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($incidents as $inc): ?>
                    <tr>
                        <td data-label="میرور"><?= htmlspecialchars(trim($inc['name_fa'] . ' ' . $inc['name_en'], ' ')) ?></td>
                        <td data-label="شروع قطعی"><?= monitorFaDateTime($inc['started_at']) ?></td>
                        <td data-label="پایان">
                            <?php if ($inc['ended_at']): ?>
                                <?= monitorFaDateTime($inc['ended_at']) ?>
                            <?php else: ?>
                                <span class="mon-badge mon-down"><span class="mon-dot"></span>ادامه دارد</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="مدت">
                            <?php if ($inc['duration_s'] !== null): ?>
                                <?= monitorDurationFa((int)$inc['duration_s']) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td data-label="دلیل" class="mon-incident-note"><?= htmlspecialchars($inc['note'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="mon-info-box">
        <h3><?= icon('info', 16) ?> پایش این صفحه چگونه انجام می‌شود؟</h3>
        <p>
            سرور <?= htmlspecialchars(SITE_NAME) ?> به‌صورت خودکار (روزی یک بار یا با بازهٔ قابل تنظیم) روی لینک همهٔ
            میرورهای فعال، یک درخواست HTTP می‌زند و زمان پاسخ، کد وضعیت و سلامت گواهی SSL را ثبت می‌کند.
            دکمهٔ «پینگ» روی کارت‌ها جداگانه و از سمت مرورگر خودتان اندازه‌گیری می‌شود؛
            اعداد این صفحه مربوط به اندازه‌گیری سمت سرور است.
        </p>
    </div>
</section>

<script>window.MH_MONITOR_REFRESH = <?= $selectedProvider ? 0 : 60 ?>;</script>
<script src="<?= asset('js/monitor.js') ?>" defer></script>
<?php renderFooter(); ?>
