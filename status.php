<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/layout.php';
require_once 'includes/monitoring.php';

$summary = monitorStatusSummary($pdo);
$rows = monitorMirrorRows($pdo);
$incidents = monitorRecentIncidents($pdo, 10);
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
    'canonical' => rtrim(SITE_URL, '/') . '/status.php',
];
renderHeader('وضعیت سرورها');
?>

<section class="section">
    <div class="section-header">
        <h1 class="section-title">وضعیت لحظه‌ای سرورها</h1>
        <span class="badge badge-primary" id="mon-last-update" title="آخرین به‌روزرسانی خودکار صفحه">
            <?= icon('activity', 13) ?> به‌روزرسانی خودکار هر <?= monitorFa(60) ?> ثانیه
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
                    — <?= monitorFa($summary['last_run_checked']) ?> میرور بررسی شد
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
            <p>میرور تحت پایش</p>
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

<section class="section">
    <h2 class="section-title">جزئیات میرورها</h2>
    <?php if (empty($rows)): ?>
        <p class="empty-state">هنوز میروری برای پایش ثبت نشده است.</p>
    <?php else: ?>
        <div class="table-container mon-table-wrap">
            <table class="data-table mon-table">
                <thead>
                    <tr>
                        <th>میرور</th>
                        <th>وضعیت</th>
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
                            <td data-label="وضعیت" class="mon-cell-status"><?= monitorStatusBadge($row) ?></td>
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
                        <td><?= htmlspecialchars(trim($inc['name_fa'] . ' ' . $inc['name_en'], ' ')) ?></td>
                        <td><?= monitorFaDateTime($inc['started_at']) ?></td>
                        <td>
                            <?php if ($inc['ended_at']): ?>
                                <?= monitorFaDateTime($inc['ended_at']) ?>
                            <?php else: ?>
                                <span class="mon-badge mon-down"><span class="mon-dot"></span>ادامه دارد</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($inc['duration_s'] !== null): ?>
                                <?= monitorDurationFa((int)$inc['duration_s']) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="mon-incident-note"><?= htmlspecialchars($inc['note'] ?? '—') ?></td>
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

<script>window.MH_MONITOR_REFRESH = 60;</script>
<script src="<?= asset('js/monitor.js') ?>" defer></script>
<?php renderFooter(); ?>
