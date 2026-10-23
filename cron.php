<?php

/* ============================================================
   MirrorHub — اجرای چرخهٔ پایش (Cron Entry)
   ------------------------------------------------------------
   این فیل ورودی رسمی زمان‌بند (cron) است. سه روش اجرا:

   ۱) خط فرمان سرور (توصیه‌شده — لینوکس):
        php /مسیر/site/cron.php
      عبارت crontab متناسب با بازهٔ انتخاب‌شده را در پنل مدیریت →
      پایش سرورها ببینید و پس از تغییر بازه، دستور هاست را نیز به‌روزرسانی کنید.

   ۲) وظیفهٔ زمان‌بندی‌شدهٔ ویندوز (WAMP):
      دستور متناسب با فاصلهٔ انتخاب‌شده در پنل مدیریت ساخته می‌شود.

   ۳) سرویس‌های web-cron (مثل cron-job.org):
        https://دامنه‌سایت/cron.php?کلید=MONITOR_CRON_KEY
      کلید را می‌توانید در پنل مدیریت → پایش سرورها ببینید.

   پارامترها:
        force=1   → اجرا حتی اگر پایش در تنظیمات غیرفعال باشد
        source=auto → مشخص می‌کند این اجرا از ماژول خودکار آمده است
   ============================================================ */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/monitoring.php';

$isCli = PHP_SAPI === 'cli';
$force = false;

/* در اجرای خط فرمان، خطاها باید در لاگ cron دیده شوند */
if ($isCli) {
    error_reporting(E_ALL);
    ini_set('display_errors', 'stderr');
}

/* کنترل دسترسی نسخهٔ وب */
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    $key = monitorCronKey($pdo);
    $given = (string)($_GET['key'] ?? '');
    if ($key === '' || $given === '' || !hash_equals($key, $given)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'کلید دسترسی نامعتبر است.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // این endpoint فقط برای GET مجاز است
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!$isCli) {
    $force = isset($_GET['force']) && $_GET['force'] === '1';
    $requestedSource = (string)($_GET['source'] ?? '');
    $source = $requestedSource === 'auto' ? 'auto' : ($requestedSource === 'manual' ? 'manual' : 'cron-web');
} else {
    $source = 'cron-cli';
    foreach (array_slice($argv ?? [], 1) as $arg) {
        if ($arg === '--force') $force = true;
    }
}

// Cron schedule is controlled by the hosting panel, so record actual invocations
// (not internal fallback triggers or one-off manual tests) to estimate its cadence.
if ($source === 'cron-cli' || $source === 'cron-web') {
    monitorRecordCronInvocation($pdo);
}

/* جلوگیری از قطع شدن اجرای خط فرمان توسط زمان‌بند وب‌سرور */
if (function_exists('set_time_limit')) {
    @set_time_limit(300);
}
@ini_set('max_execution_time', '300');

$result = monitorRunCycle($pdo, ['force' => $force, 'source' => $source]);

if ($isCli) {
    if (!empty($result['skipped'])) {
        echo "[پایش] اجرا نشد: {$result['reason']}\n";
        exit(0);
    }
    echo "[پایش] چرخه کامل شد — بررسی‌شده: {$result['checked']}"
        . " | آنلاین: {$result['up']}"
        . " | کند: {$result['slow']}"
        . " | قطع: {$result['down']}"
        . " | بدون بررسی: {$result['skipped_targets']}"
        . " | هشدار: {$result['alerts']}"
        . " | مدت: {$result['duration_ms']}ms\n";
    exit(0);
}

http_response_code(empty($result['skipped']) ? 200 : 202);
echo json_encode(['success' => empty($result['skipped']), 'data' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
