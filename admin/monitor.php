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
            flash('success', 'چرخهٔ پایش اجرا شد: ' . monitorFa($lastRunResult['checked']) . ' میرور بررسی شد (آنلاین: '
                . monitorFa($lastRunResult['up']) . '، کند: ' . monitorFa($lastRunResult['slow']) . '، قطع: '
                . monitorFa($lastRunResult['down']) . ').');
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
$settings = monitorSettings($pdo);
$summary = monitorStatusSummary($pdo);
$rows = monitorMirrorRows($pdo);
$incidents = monitorRecentIncidents($pdo, 10);
$cronKey = monitorCronKey($pdo);
$cronUrl = SITE_URL . '/cron.php?key=' . ($cronKey !== '' ? htmlspecialchars($cronKey) : 'YOUR_KEY');
$scheme = parse_url(SITE_URL, PHP_URL_SCHEME) ?: 'https';

renderHeader('پایش سرورها', true);
?>

<section class="section">
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
        پایشگر به‌صورت خودکار هر <strong><?= monitorFa(number_format((float)$settings['interval_hours'])) ?> ساعت</strong>
        فقط ارائه‌دهندگانِ فعال را بررسی می‌کند، آمار را ثبت می‌کند و در صورت قطعی هشدار می‌فرستد.
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

<section class="section">
    <h2 class="section-title">وضعیت میرورها</h2>
    <?php if (empty($rows)): ?>
        <p class="empty-state">میروری برای پایش وجود ندارد.</p>
    <?php else: ?>
        <div class="table-container mon-table-wrap">
            <table class="data-table mon-table">
                <thead>
                    <tr>
                        <th>میرور</th>
                        <th>وضعیت</th>
                        <th>پاسخ</th>
                        <th>کد</th>
                        <th>آپتایم ۳۰ روز</th>
                        <th>روند اخیر</th>
                        <th>آخرین بررسی</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                            $names = mirrorDisplayName($row);
                            $daily = monitorUptimeSeries($pdo, (int)$row['id'], 30);
                            $latency = monitorLatencySeries($pdo, (int)$row['id'], 30);
                        ?>
                        <tr class="mon-row mon-row-<?= htmlspecialchars($row['status']) ?>">
                            <td data-label="میرور">
                                <strong><?= htmlspecialchars($names['fa']) ?></strong>
                                <a class="ltr-text mon-url" href="<?= htmlspecialchars($row['url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars(excerpt($row['url'], 40)) ?></a>
                            </td>
                            <td data-label="وضعیت"><?= monitorStatusBadge($row) ?></td>
                            <td data-label="پاسخ"><?= $row['last_response_ms'] !== null ? monitorFa((int)$row['last_response_ms']) . ' ms' : '—' ?></td>
                            <td data-label="کد"><?= $row['last_http_code'] ? monitorFa((int)$row['last_http_code']) : '—' ?></td>
                            <td data-label="آپتایم">
                                <div class="mon-uptime-num"><?= $row['uptime_percent'] !== null ? monitorFa(number_format((float)$row['uptime_percent'], 1)) . '٪' : '—' ?></div>
                                <?= monitorUptimeBar($daily, 30) ?>
                            </td>
                            <td data-label="روند"><?= $latency ? monitorSparkline($latency) : '<span class="mon-no-data">—</span>' ?></td>
                            <td data-label="آخرین بررسی"><?= monitorTimeAgoFa($row['last_checked_at']) ?></td>
                            <td data-label="عملیات" class="actions">
                                <form method="POST" action="<?= SITE_URL ?>/admin/monitor.php" class="inline-form" title="پاک کردن تاریخچهٔ این میرور">
                                    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= generateCsrfToken() ?>">
                                    <input type="hidden" name="req_action" value="purge_history">
                                    <input type="hidden" name="mirror_id" value="<?= (int)$row['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline btn-danger" onclick="return confirm('تاریخچهٔ پایش این میرور پاک شود؟')">پاک‌سازی</button>
                                </form>
                            </td>
                        </tr>
                        <?php if ($row['status'] === 'down' && !empty($row['last_error'])): ?>
                            <tr class="mon-error-row">
                                <td colspan="8" class="mon-error-cell">
                                    <?= icon('alert-triangle', 13) ?>
                                    آخرین خطا برای «<?= htmlspecialchars($names['fa']) ?>»:
                                    <span class="ltr-text"><?= htmlspecialchars($row['last_error']) ?></span>
                                    <?php if (!empty($row['down_since'])): ?>
                                        — قطعی از <?= monitorFaDateTime($row['down_since']) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="section">
    <h2 class="section-title">زمان‌بندی اجرای خودکار</h2>
    <div class="mon-info-box">
        <p>
            چرخهٔ پایش با ۳ روش قابل زمان‌بندی است (توضیح کامل در <span class="ltr-text">docs/monitoring.md</span>):
        </p>
        <ol class="mon-cron-list">
            <li>
                <strong>cron سرور (توصیه‌شده):</strong>
                <code class="ltr-text">0 * * * * php <?= htmlspecialchars(rtrim(str_replace('\\', '/', dirname(__DIR__)), '/')) ?>/cron.php >> <?= htmlspecialchars(rtrim(str_replace('\\', '/', dirname(__DIR__)), '/')) ?>/cron/monitor.log 2>&1</code>
            </li>
            <li>
                <strong>وظیفهٔ زمان‌بندی‌شدهٔ ویندوز:</strong>
                <code class="ltr-text">schtasks /Create /TN "MirrorHub-Monitor" /SC DAILY /ST 04:15 /TR "\"C:\wamp64\bin\php\php8.2.26\php.exe\" \"<?= htmlspecialchars(dirname(__DIR__) . '\cron.php') ?>\""</code>
            </li>
            <li>
                <strong>سرویس‌های web-cron:</strong> این آدرس را در سرویسی مثل cron-job.org با تکرار ساعتی ثبت کنید:
                <code class="ltr-text" dir="ltr"><?= $cronUrl ?></code>
                <a href="<?= $cronUrl ?>" class="btn btn-sm btn-outline" target="_blank" rel="noopener noreferrer">تست این آدرس</a>
                <span class="form-hint">با باز کردن این آدرس، یک چرخهٔ کامل اجرا می‌شود و پاسخ JSON می‌بینید؛ کلید دسترسی را می‌توانید از تنظیمات زیر ببینید.</span>
            </li>
        </ol>
        <p class="mon-hint-line">
            اگر هیچ‌کدام در دسترس نباشد، خود سایت هنگام بازدید کاربران و به‌صورت خودکار چرخه را اجرا می‌کند
            (حداکثر یک بار در هر <?= monitorFa(number_format((float)$settings['interval_hours'])) ?> ساعت).
        </p>
    </div>
</section>

<section class="section">
    <h2 class="section-title">تنظیمات پایش</h2>
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
                    <label for="timeout_seconds">مهلت پاسخ هر لینک (ثانیه)</label>
                    <input type="number" id="timeout_seconds" name="timeout_seconds" min="2" max="30" value="<?= htmlspecialchars($settings['timeout_seconds']) ?>">
                    <span class="form-hint">بین ۲ تا ۳۰؛ اگر لینک تا این زمان پاسخ ندهد «قطعی» ثبت می‌شود.</span>
                </div>
                <div class="form-group">
                    <label for="slow_threshold_ms">آستانهٔ «کند» (میلی‌ثانیه)</label>
                    <input type="number" id="slow_threshold_ms" name="slow_threshold_ms" min="100" max="30000" value="<?= htmlspecialchars($settings['slow_threshold_ms']) ?>">
                    <span class="form-hint">زمان پاسخ بالاتر از این مقدار، وضعیت «کند» می‌گیرد (آپتایم حساب می‌شود).</span>
                </div>
                <div class="form-group">
                    <label for="interval_hours">فاصلهٔ پایش خودکار (ساعت)</label>
                    <input type="number" id="interval_hours" name="interval_hours" min="1" max="168" value="<?= htmlspecialchars($settings['interval_hours']) ?>">
                    <span class="form-hint">این بازه فقط برای پایش ارائه‌دهندگان است؛ زمان‌بند سرور باید ساعتی اجرا شود.</span>
                </div>
                <div class="form-group">
                    <label for="retention_days">مدت نگهداری تاریخچه (روز)</label>
                    <input type="number" id="retention_days" name="retention_days" min="7" max="730" value="<?= htmlspecialchars($settings['retention_days']) ?>">
                    <span class="form-hint">بررسی‌های قدیمی‌تر خودکار حذف می‌شوند.</span>
                </div>
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
                    <label for="batch_limit">حداکثر ارائه‌دهنده در هر چرخه</label>
                    <input type="number" id="batch_limit" name="batch_limit" min="1" max="1000" value="<?= htmlspecialchars($settings['batch_limit']) ?>">
                    <span class="form-hint">میرورهایی که دیرتر بررسی شده‌اند در اولویت هستند تا فهرست‌های بزرگ از قلم نیفتند.</span>
                </div>
                <div class="form-group">
                    <label for="failure_threshold">تعداد خطای متوالی برای ثبت قطعی</label>
                    <input type="number" id="failure_threshold" name="failure_threshold" min="1" max="5" value="<?= htmlspecialchars($settings['failure_threshold']) ?>">
                    <span class="form-hint">برای کاهش قطعی کاذب؛ هر بررسی ناموفق یک بار دیگر هم امتحان می‌شود.</span>
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
                        <td><?= htmlspecialchars(trim($inc['name_fa'] . ' ' . $inc['name_en'], ' ')) ?></td>
                        <td><?= monitorFaDateTime($inc['started_at']) ?></td>
                        <td>
                            <?php if ($inc['ended_at']): ?>
                                <?= monitorFaDateTime($inc['ended_at']) ?>
                            <?php else: ?>
                                <span class="mon-badge mon-down"><span class="mon-dot"></span>ادامه دارد</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $inc['duration_s'] !== null ? monitorDurationFa((int)$inc['duration_s']) : '—' ?></td>
                        <td class="mon-incident-note"><?= htmlspecialchars($inc['note'] ?? '—') ?></td>
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
                            <td><?= monitorFaDateTime($check['checked_at']) ?></td>
                            <td><?= htmlspecialchars(trim($check['name_fa'] . ' ' . $check['name_en'], ' ')) ?></td>
                            <td><span class="mon-badge mon-sm mon-<?= htmlspecialchars($check['status']) ?>"><span class="mon-dot"></span><?= monitorStatusFa($check['status']) ?></span></td>
                            <td><?= $check['response_ms'] !== null ? monitorFa((int)$check['response_ms']) . ' ms' : '—' ?></td>
                            <td><?= $check['http_code'] ? monitorFa((int)$check['http_code']) : '—' ?></td>
                            <td class="ltr-text"><?= htmlspecialchars($check['method']) ?></td>
                            <td class="mon-incident-note"><?= htmlspecialchars($check['error'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php renderFooter(); ?>
