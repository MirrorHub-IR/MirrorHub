<?php

require_once __DIR__ . '/jalali.php';

/* ============================================================
   MirrorHub — سامانه پایش میرورها (Server-side Monitoring)
   ------------------------------------------------------------
   این فایل هستهٔ اصلی «پایشگر» سایت است:

   • طبق بازه‌ای که در پنل مدیریت انتخاب می‌شود، روی لینک میرورهای فعال
     متصل به دستهٔ «ارائه‌دهنده» پینگ سمت سرور (HTTP/HTTPS) انجام می‌شود.
   • نتیجهٔ هر بررسی (زمان پاسخ، کد HTTP، خطای احتمالی و...) در
     جدول mirror_checks ثبت و آمار روزانه/۳۰ روزه به‌روز می‌شود.
   • وضعیت لحظه‌ای هر میرور (آنلاین / کند / قطع) در mirror_status
     نگهداری می‌شود و کارت‌های سایت همان وضعیت را نشان می‌دهند.
   • قطعی‌ها در mirror_incidents ثبت و در صورت تنظیم، هشدار
     تلگرام/وب‌هوک ارسال می‌شود.
   • اجرا از سه راه ممکن است: cron سرور (cron.php)، وظیفهٔ
     زمان‌بندی‌شدهٔ ویندوز، یا به‌صورت خودکار هنگام بازدید کاربران
     (در صورت نبود cron) — توضیح کامل در docs/monitoring.md
   ============================================================ */

const MONITOR_SCHEMA_VERSION = 1;
const MONITOR_DEFAULT_CRON_HOURS = 1;

/* برخی بیلدهای PHP (به‌خصوص روی ویندوز) ثابت‌های خطای cURL را
   صادر نمی‌کنند؛ در صورت نبود، از مقادیر استاندارد استفاده می‌شود. */
foreach ([
    'CURLE_COULDNT_RESOLVE_HOST'         => 6,
    'CURLE_COULDNT_CONNECT'              => 7,
    'CURLE_OPERATION_TIMEDOUT'           => 28,
    'CURLE_SSL_CONNECT_ERROR'            => 35,
    'CURLE_PEER_FAILED_VERIFICATION'     => 60,
    'CURLE_GOT_NOTHING'                  => 52,
    'CURLE_RECV_ERROR'                   => 56,
    'CURLE_TOO_MANY_REDIRECTS'           => 47,
    'CURLE_WRITE_ERROR'                  => 23,
] as $__mhConst => $__mhVal) {
    if (!defined($__mhConst)) define($__mhConst, $__mhVal);
}

/* ============================================================
   تنظیمات (monitor_settings)
   ============================================================ */

/** بازه‌های پیشنهادی در پنل؛ مقدارهای قدیمی هم برای سازگاری حفظ می‌شوند. */
function monitorIntervalChoices(): array {
    return [1, 2, 3, 4, 6, 8, 12, 24, 48, 72, 168];
}

function monitorConfiguredIntervalHours(array $settings): int {
    return max(1, min(168, (int)($settings['interval_hours'] ?? 1)));
}

/** عبارت cron متناسب با بازهٔ پایش ذخیره‌شده در برنامه. */
function monitorCronExpression(int $hours): string {
    $hours = max(1, min(168, $hours));
    if ($hours >= 24) {
        // زمان‌بند روزانه فراخوانی می‌شود؛ برنامه با last_run_at بازه‌های چندروزه را اعمال می‌کند.
        return '0 0 * * *';
    }
    if (in_array($hours, [1, 2, 3, 4, 6, 8, 12], true)) {
        return '0 */' . $hours . ' * * *';
    }
    // سازگاری با تنظیم‌های دلخواه قدیمی: cron ساعتی فراخوانی می‌شود و
    // monitorRunCycle تضمین می‌کند که چرخه زودتر از بازهٔ انتخابی اجرا نشود.
    return '0 * * * *';
}

function monitorIntervalLabel(int $hours): string {
    $hours = max(1, min(168, $hours));
    if ($hours === 24) return 'روزی یک‌بار (هر ۲۴ ساعت)';
    if ($hours === 48) return 'هر ۲ روز یک‌بار';
    if ($hours === 72) return 'هر ۳ روز یک‌بار';
    if ($hours === 168) return 'هفته‌ای یک‌بار';
    return 'هر ' . monitorFa($hours) . ' ساعت';
}

function monitorDefaults(): array {
    return [
        'enabled'             => '1',     // ۱ = پایش فعال، ۰ = غیرفعال
        'timeout_seconds'     => '8',     // حداکثر انتظار برای پاسخ هر لینک
        'slow_threshold_ms'   => '1200',  // از این زمان به بعد، وضعیت «کند» محسوب می‌شود
        'interval_hours'      => '1',     // فاصلهٔ چرخهٔ واقعی پایش در پنل مدیریت (ساعت)
        'batch_limit'          => '200',   // سقف هدف در هر چرخه؛ برای اجرای سبک‌تر
        'failure_threshold'    => '2',     // تعداد خطاهای متوالی پیش از اعلام قطعی
        'retention_days'      => '180',   // نگهداری تاریخچهٔ بررسی‌ها به روز
        'uptime_window_days'  => '30',    // پنجرهٔ محاسبهٔ آپتایم
        'alert_down'          => '1',     // هشدار هنگام قطع شدن میرور
        'alert_recovery'      => '1',     // هشدار هنگام برگشت میرور
        'bale_bot_token'      => '',      // توکن ربات بله (اختیاری)
        'bale_chat_id'        => '',      // شناسهٔ مقصد پیام در بله (اختیاری)
        'telegram_bot_token'  => '',      // سازگاری با تنظیمات قدیمی؛ دیگر استفاده نمی‌شود
        'telegram_chat_id'    => '',      // سازگاری با تنظیمات قدیمی؛ دیگر استفاده نمی‌شود
        'webhook_url'         => '',      // آدرس وب‌هوک دلخواه برای دریافت هشدارها (اختیاری)
        'last_run_at'         => '',
        'last_cycle_started_at' => '',
        'last_run_ms'         => '0',
        'last_run_checked'    => '0',
        'last_attempt_at'     => '',
        'previous_cron_run_at'=> '',
        'last_cron_run_at'    => '',
        'cron_key'            => '',      // کلید دسترسی endpoint زمان‌بند وب
    ];
}

function monitorSettings(PDO $pdo): array {
    if (isset($GLOBALS['__mh_monitor_settings']) && is_array($GLOBALS['__mh_monitor_settings'])) {
        return $GLOBALS['__mh_monitor_settings'];
    }

    $settings = monitorDefaults();
    try {
        foreach ($pdo->query("SELECT skey, svalue FROM monitor_settings") as $row) {
            if (array_key_exists($row['skey'], $settings)) {
                $settings[$row['skey']] = (string)$row['svalue'];
            }
        }
    } catch (Throwable $e) {
        // جدول هنوز ساخته نشده است؛ همان مقادیر پیش‌فرض برگردانده می‌شود.
    }
    $GLOBALS['__mh_monitor_settings'] = $settings;
    return $settings;
}

function monitorSetting(PDO $pdo, string $key): string {
    $settings = monitorSettings($pdo);
    return $settings[$key] ?? '';
}

function monitorSaveSettings(PDO $pdo, array $values): void {
    $defaults = monitorDefaults();
    $stmt = $pdo->prepare("INSERT INTO monitor_settings (skey, svalue) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)");
    foreach ($values as $key => $value) {
        if (!array_key_exists($key, $defaults)) continue;
        $stmt->execute([(string)$key, (string)$value]);
    }
    // کش این درخواست را تازه کن
    monitorSettingsReset();
}

function monitorSettingsReset(): void {
    // تازه‌سازی کشِ داخلِ همان درخواست، پس از ذخیرهٔ تنظیمات
    unset($GLOBALS['__mh_monitor_settings']);
}

/** ثبت زمان اجرای واقعی cron بیرونی؛ برای نمایش زمان‌بندی مشاهده‌شده در صفحهٔ عمومی */
function monitorRecordCronInvocation(PDO $pdo): void {
    $settings = monitorSettings($pdo);
    $previous = trim((string)($settings['last_cron_run_at'] ?? ''));
    monitorSaveSettings($pdo, [
        'previous_cron_run_at' => $previous,
        'last_cron_run_at' => date('Y-m-d H:i:s'),
    ]);
}

/* کلید cron وب (برای فراخوانی cron.php از سرویس‌های web-cron) */
function monitorCronKey(PDO $pdo): string {
    if (defined('MONITOR_CRON_KEY') && MONITOR_CRON_KEY !== '') {
        return (string)MONITOR_CRON_KEY;
    }
    try {
        $key = monitorSetting($pdo, 'cron_key');
        if ($key === '') {
            $key = bin2hex(random_bytes(20));
            $pdo->prepare("INSERT INTO monitor_settings (skey, svalue) VALUES ('cron_key', ?)
                ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)")->execute([$key]);
        }
        return $key;
    } catch (Throwable $e) {
        return '';
    }
}

/* ============================================================
   ساخت جداول (خودترمیم‌کننده — امن در برابر اجرای چندباره)
   ============================================================ */

function monitorTableDDL(): array {
    $fk = "FOREIGN KEY (`mirror_id`) REFERENCES `mirrors`(`id`) ON DELETE CASCADE";
    return [
        "CREATE TABLE IF NOT EXISTS `mirror_checks` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `mirror_id` INT NOT NULL,
            `checked_at` DATETIME NOT NULL,
            `status` ENUM('up','slow','down') NOT NULL,
            `response_ms` INT UNSIGNED DEFAULT NULL,
            `ttfb_ms` INT UNSIGNED DEFAULT NULL,
            `http_code` INT DEFAULT NULL,
            `method` VARCHAR(8) DEFAULT 'HEAD',
            `redirects` TINYINT UNSIGNED DEFAULT 0,
            `final_url` VARCHAR(1000) DEFAULT NULL,
            `ip_addr` VARCHAR(45) DEFAULT NULL,
            `error` VARCHAR(500) DEFAULT NULL,
            KEY `idx_checks_mirror_time` (`mirror_id`, `checked_at`),
            KEY `idx_checks_time` (`checked_at`),
            $fk
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `mirror_status` (
            `mirror_id` INT PRIMARY KEY,
            `status` ENUM('unknown','up','slow','down') DEFAULT 'unknown',
            `last_checked_at` DATETIME DEFAULT NULL,
            `last_response_ms` INT UNSIGNED DEFAULT NULL,
            `last_http_code` INT DEFAULT NULL,
            `last_error` VARCHAR(500) DEFAULT NULL,
            `down_since` DATETIME DEFAULT NULL,
            `consecutive_failures` INT UNSIGNED DEFAULT 0,
            `uptime_percent` DECIMAL(5,2) DEFAULT NULL,
            `avg_response_ms` INT UNSIGNED DEFAULT NULL,
            `checks_total` INT UNSIGNED DEFAULT 0,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            $fk
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `mirror_daily_stats` (
            `mirror_id` INT NOT NULL,
            `day` DATE NOT NULL,
            `checks` INT UNSIGNED DEFAULT 0,
            `up` INT UNSIGNED DEFAULT 0,
            `slow` INT UNSIGNED DEFAULT 0,
            `down` INT UNSIGNED DEFAULT 0,
            `sum_ms` BIGINT UNSIGNED DEFAULT 0,
            `min_ms` INT UNSIGNED DEFAULT NULL,
            `max_ms` INT UNSIGNED DEFAULT NULL,
            PRIMARY KEY (`mirror_id`, `day`),
            $fk
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `mirror_incidents` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `mirror_id` INT NOT NULL,
            `status` ENUM('down','slow') DEFAULT 'down',
            `started_at` DATETIME NOT NULL,
            `ended_at` DATETIME DEFAULT NULL,
            `duration_s` INT UNSIGNED DEFAULT NULL,
            `note` VARCHAR(250) DEFAULT NULL,
            KEY `idx_inc_mirror` (`mirror_id`, `started_at`),
            KEY `idx_inc_open` (`ended_at`),
            $fk
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `monitor_settings` (
            `skey` VARCHAR(50) PRIMARY KEY,
            `svalue` TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
}

/**
 * جداول پایش را در صورت نبود می‌سازد. فقط کوئری «نمایش جداول» اجرا
 * می‌شود؛ اگر همه چیز موجود باشد هزینهٔ هر درخواست یک کوئری است.
 */
function ensureMonitorSchema(PDO $pdo): bool {
    static $done = false;
    if ($done) return true;

    $required = ['mirror_checks', 'mirror_status', 'mirror_daily_stats', 'mirror_incidents', 'monitor_settings'];
    try {
        $existing = [];
        foreach ($pdo->query("SHOW TABLES") as $row) {
            $existing[] = (string)array_values($row)[0];
        }
        $missing = array_diff($required, $existing);
        if (empty($missing)) {
            $done = true;
            return true;
        }
        foreach (monitorTableDDL() as $ddl) {
            $pdo->exec($ddl);
        }
        $done = true;
        return true;
    } catch (Throwable $e) {
        // در صورت خطا (مثلاً نبود جدول mirrors هنگام نصب) بی‌صدا رد می‌شویم.
        return false;
    }
}

/* ============================================================
   اجرای یک پینگ سمت سرور (cURL + fallback با stream ها)
   ============================================================ */

/**
 * آیا PHP روی این سرور هیچ CA bundle ندارد؟ (رایج روی ویندوز)
 * در این حالت اعتبارسنجی گواهی SSL عملاً شکست می‌خورد در حالی که
 * مشکل از سرور مقصد نیست؛ بنابراین پایشگر یک بار بدون اعتبارسنجی
 * تلاش می‌کند و نتیجه را با یادداشت ثبت می‌نماید.
 */
function monitorCaBundleMissing(): bool {
    $cainfo = trim((string)ini_get('curl.cainfo'));
    $cafile = trim((string)ini_get('openssl.cafile'));
    $capath = trim((string)ini_get('openssl.capath'));
    return $cainfo === '' && $cafile === '' && $capath === '';
}

/**
 * یک بررسی کامل روی یک لینک: اتصال، TLS، زمان دریافت اولین بایت و
 * کد پاسخ HTTP. ابتدا HEAD می‌زنیم؛ اگر سرور HEAD را رد کند، GET
 * می‌کنیم و به‌محز دریافت اولین بایت، انتقال را قطع می‌کنیم تا حجم
 * دانلود صفر بماند.
 */
function monitorProbeUrl(string $url, float $timeoutSec = 8.0): array {
    $result = [
        'ok'           => false,
        'http_code'    => 0,
        'response_ms'  => null,
        'ttfb_ms'      => null,
        'dns_ms'       => null,
        'connect_ms'   => null,
        'tls_ms'       => null,
        'redirects'    => 0,
        'final_url'    => $url,
        'ip'           => null,
        'content_type' => null,
        'method'       => 'HEAD',
        'error'        => null,
        'ssl_verified' => true,
    ];

    $timeoutSec = max(1.0, min(30.0, $timeoutSec));

    if (!function_exists('curl_init')) {
        return monitorProbeUrlStreams($url, $timeoutSec, $result);
    }

    $ua = 'Mozilla/5.0 (compatible; ' . SITE_NAME . '-Monitor/1.0; +monitor.' . parse_url(SITE_URL, PHP_URL_HOST) . ')';

    $baseOpts = [
        CURLOPT_URL            => $url,
        CURLOPT_USERAGENT      => $ua,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT  => (int)max(1, min(5, (int)$timeoutSec)),
        CURLOPT_TIMEOUT        => (int)ceil($timeoutSec),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HEADER         => false,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_ENCODING       => '',   // هرچه سرور فرستد
    ];
    if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
        $baseOpts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
    }

    $run = function (array $extra) use ($baseOpts) {
        $ch = curl_init();
        curl_setopt_array($ch, $baseOpts + $extra);
        curl_exec($ch);
        $info = [
            'http_code'    => (int)curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'total_ms'     => (int)round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000),
            'ttfb_ms'      => (int)round(curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME) * 1000),
            'dns_ms'       => (int)round(curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME) * 1000),
            'connect_ms'   => (int)round(curl_getinfo($ch, CURLINFO_CONNECT_TIME) * 1000),
            'tls_ms'       => (int)round(curl_getinfo($ch, CURLINFO_APPCONNECT_TIME) * 1000),
            'redirects'    => (int)curl_getinfo($ch, CURLINFO_REDIRECT_COUNT),
            'ip'           => (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP),
            'content_type' => (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
            'final_url'    => (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL),
            'errno'        => curl_errno($ch),
            'error'        => (string)curl_error($ch),
        ];
        curl_close($ch);
        return $info;
    };

    /* ۱) درخواست HEAD — سبک‌ترین راه اندازه‌گیری */
    $info = $run([CURLOPT_NOBODY => true, CURLOPT_TIMEOUT => (int)ceil($timeoutSec)]);

    /* ۲) اگر سرور HEAD را قبول نداشت، با GET امتحان می‌کنیم و
          به‌محض اولین بایتِ بدنه، انتقال را قطع می‌کنیم. */
    $headRejectedCodes = [400, 401, 403, 404, 405, 406, 411, 415, 500, 501, 505];
    if ($info['http_code'] > 0 && in_array($info['http_code'], $headRejectedCodes, true)) {
        $info = $run([
            CURLOPT_NOBODY       => false,
            CURLOPT_TIMEOUT      => (int)ceil($timeoutSec) + 1,
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) {
                return 0; // قطع بلافاصلهٔ بدنه؛ فقط هدر و زمان‌ها مهم‌اند
            },
        ]);
        $result['method'] = 'GET';
        if ($info['errno'] === CURLE_WRITE_ERROR) {
            $info['errno'] = 0;   // قطع عمدی بدنه بود، نه خطا
            $info['error'] = '';
        }
    }

    /* گواهی TLS هرگز دور زده نمی‌شود؛ خطای CA باید به‌عنوان خطای محیط پایشگر دیده شود. */

    $result['http_code']    = $info['http_code'];
    $result['redirects']    = $info['redirects'];
    $result['final_url']    = $info['final_url'] ?: $url;
    $result['ip']           = $info['ip'] ?: null;
    $result['content_type'] = $info['content_type'] ?: null;
    $result['dns_ms']       = $info['dns_ms'];
    $result['connect_ms']   = $info['connect_ms'];
    $result['tls_ms']       = $info['tls_ms'] ?: null;
    $result['ttfb_ms']      = $info['ttfb_ms'] ?: null;
    $result['ssl_verified'] = $sslVerified;

    if ($info['errno'] !== 0) {
        $result['error'] = monitorCurlErrorMessage($info['errno'], $info['error']);
        return $result;
    }

    $result['ok']          = true;
    $result['response_ms'] = max(1, $info['total_ms']);
    return $result;
}

/** ترجمهٔ خطاهای cURL به متن فارسی گویا */
function monitorCurlErrorMessage(int $errno, string $raw): string {
    $raw = trim($raw);
    switch ($errno) {
        case CURLE_COULDNT_RESOLVE_HOST:
            return 'نام دامنه resolve نشد (DNS)' . ($raw ? " — $raw" : '');
        case CURLE_COULDNT_CONNECT:
            return 'اتصال برقرار نشد (سرور پاسخ نمی‌دهد یا پورت بسته است)' . ($raw ? " — $raw" : '');
        case CURLE_OPERATION_TIMEDOUT:
            return 'پاسخی در مهلت مقرر دریافت نشد (timeout)' . ($raw ? " — $raw" : '');
        case CURLE_SSL_CONNECT_ERROR:
        case CURLE_PEER_FAILED_VERIFICATION:
            return 'خطا در مذاکرهٔ TLS/SSL (گواهی نامعتبر یا ناسازگاری TLS)' . ($raw ? " — $raw" : '');
        case CURLE_GOT_NOTHING:
            return 'سرور هیچ پاسخی برنگرداند' . ($raw ? " — $raw" : '');
        case CURLE_RECV_ERROR:
            return 'خطا هنگام دریافت پاسخ از سرور' . ($raw ? " — $raw" : '');
        case CURLE_TOO_MANY_REDIRECTS:
            return 'بیش از حد مجاز ریدایرکت شد' . ($raw ? " — $raw" : '');
    }
    return ($raw !== '' ? $raw : "خطای cURL #$errno");
}

/** نسخهٔ جایگزین بدون cURL (stream های PHP) */
function monitorProbeUrlStreams(string $url, float $timeoutSec, array $result): array {
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'timeout'       => $timeoutSec,
            'ignore_errors' => true,
            'follow_location' => 1,
            'max_redirects' => 5,
            'header'        => "User-Agent: " . SITE_NAME . "-Monitor/1.0\r\nAccept: */*\r\n",
        ],
        'ssl' => [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
        ],
    ]);
    $result['method'] = 'GET';
    $started = microtime(true);
    $body = @file_get_contents($url, false, $ctx, 0, 64); // فقط ۶۴ بایت اول
    $elapsedMs = (int)round((microtime(true) - $started) * 1000);

    $headerBlob = '';
    foreach (($http_response_header ?? []) as $h) $headerBlob .= $h . "\n";
    $code = 0;
    if (preg_match('#HTTP/\S+\s+(\d{3})#', $headerBlob, $m)) $code = (int)$m[1];

    $result['http_code'] = $code;
    $result['redirects'] = substr_count($headerBlob, 'HTTP/') > 1 ? substr_count($headerBlob, 'HTTP/') - 1 : 0;

    if ($body === false && $code === 0) {
        $result['error'] = 'اتصال برقرار نشد (cURL در دسترس نیست و stream هم پاسخی نداد)';
        return $result;
    }
    $result['ok'] = true;
    $result['response_ms'] = max(1, $elapsedMs);
    $result['ttfb_ms'] = $elapsedMs;
    return $result;
}

/** فقط لینک‌های http/https قابل پایش سمت سرور هستند */
function monitorIsCheckableUrl(string $url): bool {
    $url = trim($url);
    if ($url === '') return false;
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

/**
 * طبقه‌بندی نتیجهٔ یک پینگ:
 *  up   → پاسخ سالم و سریع
 *  slow → پاسخ سالم ولی کند (یا ۴۰۱/۴۰۳ که احتمالاً ربات‌بلاک است)
 *  down → خطا، تایم‌اوت یا کد ۴xx/۵xx
 */
function monitorClassify(array $probe, int $slowMs): array {
    if (!$probe['ok'] || $probe['response_ms'] === null) {
        return ['down', (string)($probe['error'] ?: 'اتصال برقرار نشد')];
    }
    $code = (int)$probe['http_code'];
    if ($code === 0) {
        return ['down', 'پاسخ HTTP نامعتبر دریافت شد'];
    }
    if ($code >= 400) {
        if (in_array($code, [401, 403], true)) {
            return ['slow', "کد $code — احتمال مسدودسازی ربات یا دسترسی محدود"];
        }
        return ['down', "کد پاسخ HTTP $code"];
    }
    $ms = (int)$probe['response_ms'];
    $note = null;
    if (empty($probe['ssl_verified'])) {
        $note = 'گواهی SSL بدون اعتبارسنجی بررسی شد (نبود CA bundle روی سرور پایشگر)';
    }
    if ($ms >= $slowMs) {
        return ['slow', ($note ? $note . ' — ' : '') . "کندی پاسخ ($ms میلی‌ثانیه)"];
    }
    return ['up', $note];
}

/* ============================================================
   چرخهٔ کامل پایش
   ============================================================ */

function monitorClampInt($value, int $min, int $max, int $fallback): int {
    $n = (int)$value;
    if ($n < $min || $n > $max) return $fallback;
    return $n;
}

/**
 * یک چرخهٔ کامل: همهٔ میرورهای فعال را پینگ می‌کند، نتایج را ثبت،
 * آمار را تجمیع و در صورت نیاز هشدار می‌فرستد.
 *
 * گزینه‌ها:
 *  force            → اجرای دستی بدون توجه به enabled
 *  budget_seconds   → سقف زمانی اجرا (برای اجرا وسط درخواست صفحه)
 *  limit            → حداکثر تعداد میرور در هر چرخه
 */
function monitorRunCycle(PDO $pdo, array $opts = []): array {
    $started = microtime(true);
    $out = [
        'skipped'  => false,
        'reason'   => '',
        'checked'  => 0,
        'up'       => 0,
        'slow'     => 0,
        'down'     => 0,
        'skipped_targets' => 0,
        'alerts'   => 0,
        'duration_ms' => 0,
    ];

    $settings = monitorSettings($pdo);
    if (empty($opts['force']) && (int)$settings['enabled'] !== 1) {
        $out['skipped'] = true;
        $out['reason'] = 'پایش در تنظیمات غیرفعال است.';
        return $out;
    }

    // اجرای زمان‌بند (cron) نباید از بازه‌ای که ادمین تعیین کرده زودتر چرخه‌ای را انجام دهد.
    // اجرای دستی پنل با force=true عمداً از این محدودیت عبور می‌کند.
    $source = (string)($opts['source'] ?? '');
    if (empty($opts['force']) && in_array($source, ['cron-cli', 'cron-web'], true)) {
        $intervalHours = monitorConfiguredIntervalHours($settings);
        $lastCycleTs = !empty($settings['last_cycle_started_at'])
            ? strtotime((string)$settings['last_cycle_started_at'])
            : (!empty($settings['last_run_at']) ? strtotime((string)$settings['last_run_at']) : false);
        // Small grace window avoids skipping a daily job due to a few seconds of scheduling jitter.
        $minimumElapsed = max(0, ($intervalHours * 3600) - 300);
        if ($lastCycleTs !== false && (time() - $lastCycleTs) < $minimumElapsed) {
            $out['skipped'] = true;
            $out['reason'] = 'بازهٔ تنظیم‌شدهٔ پایش (' . $intervalHours . ' ساعت) هنوز نگذشته است.';
            return $out;
        }
    }

    if (!ensureMonitorSchema($pdo)) {
        $out['skipped'] = true;
        $out['reason'] = 'جدول‌های پایش ساخته نشدند.';
        return $out;
    }

    /* قفل سراسری تا دو چرخه همزمان اجرا نشود */
    try {
        $gotLock = (bool)$pdo->query("SELECT GET_LOCK('mirrorhub_monitor_cycle', 0)")->fetchColumn();
    } catch (Throwable $e) {
        $gotLock = true; // اگر GET_LOCK در دسترس نبود، ادامه می‌دهیم
    }
    if (!$gotLock) {
        $out['skipped'] = true;
        $out['reason'] = 'یک چرخهٔ دیگر همزمان در حال اجراست.';
        return $out;
    }

    try {
        // Record the start time separately from completion so long cycles don't delay the next scheduled slot.
        monitorSaveSettings($pdo, ['last_cycle_started_at' => date('Y-m-d H:i:s')]);

        $timeout   = monitorClampInt($settings['timeout_seconds'], 2, 30, 8);
        $slowMs    = monitorClampInt($settings['slow_threshold_ms'], 100, 30000, 1200);
        $retention = monitorClampInt($settings['retention_days'], 7, 730, 180);
        $winDays   = monitorClampInt($settings['uptime_window_days'], 7, 365, 30);
        $limit     = monitorClampInt($opts['limit'] ?? $settings['batch_limit'] ?? 200, 1, 1000, 200);
        $failureThreshold = monitorClampInt($settings['failure_threshold'] ?? 2, 1, 5, 2);
        $deadline  = microtime(true) + (float)($opts['budget_seconds'] ?? 600);

        // فقط میرورهایی که به حداقل یک دستهٔ «ارائه‌دهنده» وصل‌اند پایش می‌شوند.
        // ترتیب بر اساس قدیمی‌ترین بررسی باعث می‌شود فهرست بزرگ‌تر از batch_limit هم در چرخه‌های بعدی نوبت بگیرد.
        $mirrors = $pdo->prepare("SELECT m.id, m.name_fa, m.name_en, m.url
            FROM mirrors m
            INNER JOIN mirror_categories mc ON mc.mirror_id = m.id
            INNER JOIN categories c ON c.id = mc.category_id AND c.type = 'provider'
            LEFT JOIN mirror_status ms ON ms.mirror_id = m.id
            WHERE m.status = 'active'
            GROUP BY m.id, m.name_fa, m.name_en, m.url, ms.last_checked_at
            ORDER BY COALESCE(ms.last_checked_at, '1970-01-01 00:00:00') ASC, m.id ASC LIMIT $limit");
        $mirrors->execute();
        $mirrors = $mirrors->fetchAll();

        $insertCheck = $pdo->prepare("INSERT INTO mirror_checks
            (mirror_id, checked_at, status, response_ms, ttfb_ms, http_code, method, redirects, final_url, ip_addr, error)
            VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $upsertStatus = $pdo->prepare("INSERT INTO mirror_status
            (mirror_id, status, last_checked_at, last_response_ms, last_http_code, last_error,
             down_since, consecutive_failures, uptime_percent, avg_response_ms, checks_total)
            VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, NULL, NULL, 1)
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                last_checked_at = NOW(),
                last_response_ms = VALUES(last_response_ms),
                last_http_code = VALUES(last_http_code),
                last_error = VALUES(last_error),
                down_since = VALUES(down_since),
                consecutive_failures = VALUES(consecutive_failures)");

        $upsertDaily = $pdo->prepare("INSERT INTO mirror_daily_stats
            (mirror_id, day, checks, up, slow, down, sum_ms, min_ms, max_ms)
            VALUES (?, CURDATE(), 1, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                checks = checks + 1,
                up = up + VALUES(up),
                slow = slow + VALUES(slow),
                down = down + VALUES(down),
                sum_ms = sum_ms + VALUES(sum_ms),
                min_ms = IF(VALUES(min_ms) IS NULL, min_ms, LEAST(IF(min_ms IS NULL, VALUES(min_ms), min_ms), VALUES(min_ms))),
                max_ms = GREATEST(IF(max_ms IS NULL, VALUES(max_ms), max_ms), VALUES(max_ms))");

        $alerts = [];

        foreach ($mirrors as $mirror) {
            if (microtime(true) > $deadline) {
                $out['skipped_targets']++;
                continue;
            }
            $url = trim((string)$mirror['url']);
            if (!monitorIsCheckableUrl($url)) {
                $out['skipped_targets']++;   // ftp/rsync/magnet و... پینگ سمت سرور ندارند
                continue;
            }

            // یک خطای گذرا به‌تنهایی قطعی محسوب نمی‌شود؛ یک بار دیگر پس از مکث کوتاه امتحان کن.
            $probe = monitorProbeUrl($url, $timeout);
            [$status, $note] = monitorClassify($probe, $slowMs);
            if ($status === 'down') {
                usleep(250000);
                $retryProbe = monitorProbeUrl($url, $timeout);
                [$retryStatus, $retryNote] = monitorClassify($retryProbe, $slowMs);
                if ($retryStatus !== 'down') {
                    $probe = $retryProbe;
                    $status = $retryStatus;
                    $note = $retryNote;
                } else {
                    $probe = $retryProbe;
                    $note = 'پس از دو تلاش ناموفق: ' . (string)$retryNote;
                }
            }

            /* وضعیت قبلی را برای تشخیص تغییر برداریم */
            $prevStmt = $pdo->prepare("SELECT status, down_since, consecutive_failures FROM mirror_status WHERE mirror_id = ?");
            $prevStmt->execute([(int)$mirror['id']]);
            $prev = $prevStmt->fetch() ?: null;
            $prevStatus = $prev['status'] ?? 'unknown';

            /* ثبت خود بررسی */
            $ms = $probe['response_ms'];
            $insertCheck->execute([
                (int)$mirror['id'],
                $status,
                $ms !== null ? (int)$ms : null,
                $probe['ttfb_ms'] !== null ? (int)$probe['ttfb_ms'] : null,
                (int)$probe['http_code'],
                $probe['method'],
                (int)$probe['redirects'],
                mb_substr((string)$probe['final_url'], 0, 1000),
                $probe['ip'] !== null ? mb_substr($probe['ip'], 0, 45) : null,
                $note !== null ? mb_substr($note, 0, 500) : null,
            ]);

            /* به‌روزرسانی وضعیت لحظه‌ای */
            $failures = $status === 'down' ? (int)($prev['consecutive_failures'] ?? 0) + 1 : 0;
            // تا رسیدن به آستانهٔ خطا، وضعیت قبلی حفظ می‌شود تا اختلال کوتاه باعث هشدار کاذب نشود.
            if ($status === 'down' && $failures < $failureThreshold && $prevStatus !== 'unknown') {
                $status = $prevStatus;
                $note = 'خطای موقت (' . $failures . '/' . $failureThreshold . '): ' . (string)$note;
            }
            $downSince = null;
            if ($status === 'down') {
                $downSince = ($prev['down_since'] ?? '') ?: date('Y-m-d H:i:s');
            }
            $upsertStatus->execute([
                (int)$mirror['id'],
                $status,
                $ms !== null ? (int)$ms : null,
                (int)$probe['http_code'],
                $note !== null ? mb_substr($note, 0, 500) : null,
                $downSince,
                $failures,
            ]);

            /* آمار روزانه */
            $upsertDaily->execute([
                (int)$mirror['id'],
                $status === 'up' ? 1 : 0,
                $status === 'slow' ? 1 : 0,
                $status === 'down' ? 1 : 0,
                ($ms !== null && $status !== 'down') ? (int)$ms : 0,
                ($ms !== null && $status !== 'down') ? (int)$ms : null,
                ($ms !== null && $status !== 'down') ? (int)$ms : null,
            ]);

            /* ثبت رخداد (قطعی) */
            monitorSyncIncident($pdo, (int)$mirror['id'], $status, $note);

            /* تغییر وضعیت → هشدار */
            if ($prevStatus !== 'unknown' && $prevStatus !== $status) {
                $alerts[] = [
                    'mirror' => $mirror,
                    'from'   => $prevStatus,
                    'to'     => $status,
                    'note'   => $note,
                    'down_since' => $prev['down_since'] ?? null,
                ];
            }

            $out[$status]++;
            $out['checked']++;
        }

        /* بازمحاسبهٔ آپتایم و میانگین برای همهٔ میرورهای فعال */
        foreach ($mirrors as $mirror) {
            monitorRefreshAggregate($pdo, (int)$mirror['id'], $winDays);
        }

        /* پاک‌سازی تاریخچهٔ قدیمی */
        try {
            $pdo->exec("DELETE FROM mirror_checks WHERE checked_at < DATE_SUB(NOW(), INTERVAL $retention DAY)");
            $pdo->exec("DELETE FROM mirror_daily_stats WHERE day < DATE_SUB(CURDATE(), INTERVAL $retention DAY)");
            $pdo->exec("UPDATE mirror_incidents SET ended_at = NOW(), duration_s = TIMESTAMPDIFF(SECOND, started_at, NOW())
                        WHERE ended_at IS NULL AND started_at < DATE_SUB(NOW(), INTERVAL $retention DAY)");
        } catch (Throwable $e) {
            // پاک‌سازی واجب نیست؛ چرخه را متوقف نمی‌کنیم
        }

        /* ثبت زمان اجرا */
        $durationMs = (int)round((microtime(true) - $started) * 1000);
        try {
            monitorSaveSettings($pdo, [
                'last_run_at'      => date('Y-m-d H:i:s'),
                'last_run_ms'      => (string)$durationMs,
                'last_run_checked' => (string)$out['checked'],
            ]);
        } catch (Throwable $e) {
        }

        /* ارسال هشدارها */
        $out['alerts'] = monitorDispatchAlerts($settings, $alerts);
        $out['duration_ms'] = $durationMs;
        return $out;
    } finally {
        try {
            $pdo->query("SELECT RELEASE_LOCK('mirrorhub_monitor_cycle')");
        } catch (Throwable $e) {
        }
    }
}

/** ثبت/بستن رخداد قطعی یک میرور بر اساس وضعیت تازه */
function monitorSyncIncident(PDO $pdo, int $mirrorId, string $status, ?string $note): void {
    try {
        $stmt = $pdo->prepare("SELECT id, status FROM mirror_incidents WHERE mirror_id = ? AND ended_at IS NULL ORDER BY id DESC LIMIT 1");
        $stmt->execute([$mirrorId]);
        $open = $stmt->fetch() ?: null;

        if ($status === 'down') {
            if ($open) {
                if ($open['status'] === 'down') return; // همچنان قطع است
                $pdo->prepare("UPDATE mirror_incidents SET ended_at = NOW(), duration_s = TIMESTAMPDIFF(SECOND, started_at, NOW()) WHERE id = ?")
                    ->execute([(int)$open['id']]);
                $open = null;
            }
            if (!$open) {
                $pdo->prepare("INSERT INTO mirror_incidents (mirror_id, status, started_at, note) VALUES (?, 'down', NOW(), ?)")
                    ->execute([$mirrorId, $note !== null ? mb_substr($note, 0, 250) : null]);
            }
            return;
        }

        /* برگشت از قطعی → بستن رخداد باز */
        if ($open && $open['status'] === 'down') {
            $pdo->prepare("UPDATE mirror_incidents SET ended_at = NOW(), duration_s = TIMESTAMPDIFF(SECOND, started_at, NOW()) WHERE id = ?")
                ->execute([(int)$open['id']]);
        }
    } catch (Throwable $e) {
        // ثبت رخداد واجب نیست
    }
}

/** بازمحاسبهٔ آپتایم/میانگین ۳۰ روزهٔ یک میرور از روی تاریخچه */
function monitorRefreshAggregate(PDO $pdo, int $mirrorId, int $days = 30): void {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total,
                    SUM(status IN ('up','slow')) AS ok,
                    AVG(CASE WHEN status IN ('up','slow') AND response_ms IS NOT NULL THEN response_ms END) AS avg_ms
                FROM mirror_checks
                WHERE mirror_id = ? AND checked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$mirrorId, $days]);
        $row = $stmt->fetch() ?: null;
        $total = (int)($row['total'] ?? 0);
        $ok = (int)($row['ok'] ?? 0);
        $uptime = $total > 0 ? round($ok * 100 / $total, 2) : null;
        $avg = $row['avg_ms'] !== null && $row['avg_ms'] !== '' ? (int)round((float)$row['avg_ms']) : null;

        $pdo->prepare("UPDATE mirror_status SET uptime_percent = ?, avg_response_ms = ?, checks_total = checks_total WHERE mirror_id = ?")
            ->execute([$uptime, $avg, $mirrorId]);
    } catch (Throwable $e) {
    }
}

/* ============================================================
   هشدارها (تلگرام + وب‌هوک دلخواه)
   ============================================================ */

function monitorStatusFa(string $status): string {
    return match ($status) {
        'up'      => 'آنلاین',
        'slow'    => 'کند',
        'down'    => 'قطعی',
        default   => 'نامشخص',
    };
}

function monitorDispatchAlerts(array $settings, array $alerts): int {
    $sent = 0;
    foreach ($alerts as $alert) {
        $to = $alert['to'];
        $from = $alert['from'];
        $name = trim($alert['mirror']['name_fa'] . ' — ' . $alert['mirror']['name_en'], ' —');
        $url = $alert['mirror']['url'];

        if ($to === 'down' && (int)($settings['alert_down'] ?? 1) === 1) {
            $text = "🔴 قطعی میرور\n«{$name}»\n{$url}\nدلیل: " . ($alert['note'] ?? 'نامشخص') . "\nزمان: " . jalaliFormatDate(time(), 'Y/m/d H:i');
            if (monitorNotify($settings, $text)) $sent++;
        } elseif ($from === 'down' && $to !== 'down' && (int)($settings['alert_recovery'] ?? 1) === 1) {
            $duration = '';
            if (!empty($alert['down_since'])) {
                $secs = max(0, time() - strtotime($alert['down_since']));
                $duration = "\nمدت قطعی: " . monitorDurationFa($secs);
            }
            $text = "🟢 برگشت میرور\n«{$name}»\n{$url}\nوضعیت تازه: " . monitorStatusFa($to) . "{$duration}\nزمان: " . jalaliFormatDate(time(), 'Y/m/d H:i');
            if (monitorNotify($settings, $text)) $sent++;
        }
    }
    return $sent;
}

/** ارسال یک پیام هشدار؛ هم به تلگرام هم به وب‌هوک تنظیم‌شده */
function monitorNotify(array $settings, string $text): bool {
    $delivered = false;

    if (!empty($settings['bale_bot_token']) && !empty($settings['bale_chat_id']) && function_exists('curl_init')) {
        $token = trim((string)$settings['bale_bot_token']);
        $chatId = trim((string)$settings['bale_chat_id']);
        // Bale Bot API follows the sendMessage method and JSON ok/error response pattern.
        $endpoint = 'https://tapi.bale.ai/bot' . rawurlencode($token) . '/sendMessage';
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['chat_id' => $chatId, 'text' => mb_substr($text, 0, 4000)]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string)$resp, true);
        if ($resp !== false && $code >= 200 && $code < 300 && is_array($json) && !empty($json['ok'])) {
            $delivered = true;
        }
    }

    if (!empty($settings['webhook_url']) && filter_var($settings['webhook_url'], FILTER_VALIDATE_URL)) {
        $ok = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($settings['webhook_url']);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode([
                    'event' => 'mirrorhub.monitor.alert',
                    'site'  => SITE_NAME,
                    'url'   => SITE_URL,
                    'text'  => $text,
                    'at'    => date('c'),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
            ]);
            $resp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $ok = $resp !== false && $code >= 200 && $code < 300;
        }
        if ($ok) $delivered = true;
    }
    return $delivered;
}

/* ============================================================
   خوداجرا (auto-trigger) — وقتی cron سرور در دسترس نیست
   ============================================================ */

/**
 * اگر چرخهٔ پایش دیر شده باشد، آن را کلید می‌زند:
 *  • درخواست‌ها هاستِ سایت را در پس‌زمینه به cron.php می‌زنند
 *    (بدون معطل کردن بازدیدکننده).
 *  • در محیط توسعه (هاست متفاوت) یا نبودن cURL، خود درخواستِ
 *    صفحه با یک بودجهٔ کوچک چرخه را اجرا می‌کند.
 * این تابع هرگز نباید خطایی به صفحهٔ اصلی تزریق کند؛ بنابراین
 * همه چیز در try/catch کامل است.
 */
function monitorMaybeRunDaily(PDO $pdo): void {
    if (PHP_SAPI === 'cli') return; // در CLI، خود cron.php چرخه را اجرا می‌کند
    try {
        $settings = monitorSettings($pdo);
        if ((int)$settings['enabled'] !== 1) return;
        if (!ensureMonitorSchema($pdo)) return;

        $intervalH = (float)($settings['interval_hours'] ?? 1);
        if ($intervalH <= 0) $intervalH = MONITOR_DEFAULT_CRON_HOURS;

        $now = time();
        $lastRun = !empty($settings['last_cycle_started_at'])
            ? strtotime($settings['last_cycle_started_at'])
            : (!empty($settings['last_run_at']) ? strtotime($settings['last_run_at']) : false);
        $lastTry = !empty($settings['last_attempt_at']) ? strtotime($settings['last_attempt_at']) : false;

        if ($lastRun !== false && ($now - $lastRun) < $intervalH * 3600) return;
        $attemptCooldown = max(300, min(3600, (int)round($intervalH * 3600)));
        if ($lastTry !== false && ($now - $lastTry) < $attemptCooldown) return; // جلوگیری از اجرای مکرر در بازدیدهای پرتعداد

        $pdo->prepare("INSERT INTO monitor_settings (skey, svalue) VALUES ('last_attempt_at', ?)
            ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)")->execute([date('Y-m-d H:i:s')]);

        $siteHost = strtolower((string)parse_url(SITE_URL, PHP_URL_HOST));
        $reqHost = strtolower((string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
        $sameHost = $siteHost !== '' && $siteHost === $reqHost;

        if ($sameHost && function_exists('curl_init')) {
            /* فراخوانی پس‌زمینهٔ cron.php — پاسخ را نمی‌خوانیم */
            $key = monitorCronKey($pdo);
            $ch = curl_init(SITE_URL . '/cron.php?key=' . urlencode($key) . '&source=auto');
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT      => SITE_NAME . '-Monitor-Trigger/1.0',
            ]);
            @curl_exec($ch);
            @curl_close($ch);
            return;
        }

        /* حالت آفلاین/توسعه: اجرای محدود داخل همین درخواست */
        monitorRunCycle($pdo, ['budget_seconds' => 12, 'limit' => 60]);
    } catch (Throwable $e) {
        // هر خطایی بی‌صدا بلعیده می‌شود تا صفحهٔ اصلی آسیب نبیند.
    }
}

/* ============================================================
   آمار و داده برای نمایش
   ============================================================ */

/** خلاصهٔ کلی وضعیت (برای بنر بالای صفحهٔ وضعیت و داشبورد) */
function monitorStatusSummary(PDO $pdo): array {
    $out = [
        'mirrors' => 0, 'up' => 0, 'slow' => 0, 'down' => 0, 'unknown' => 0,
        'avg_response_ms' => null, 'uptime_percent' => null,
        'last_run_at' => null, 'last_run_ms' => 0, 'last_run_checked' => 0,
        'last_cron_run_at' => null, 'previous_cron_run_at' => null,
        'observed_cron_interval_seconds' => null,
        'open_incidents' => 0, 'total_checks' => 0,
    ];
    try {
        $out['mirrors'] = (int)$pdo->query("SELECT COUNT(DISTINCT m.id) FROM mirrors m
            INNER JOIN mirror_categories mc ON mc.mirror_id = m.id
            INNER JOIN categories c ON c.id = mc.category_id AND c.type = 'provider'
            WHERE m.status = 'active'")->fetchColumn();

        $stmt = $pdo->query("SELECT COALESCE(ms.status, 'unknown') AS status, COUNT(DISTINCT m.id) AS c
            FROM mirrors m
            INNER JOIN mirror_categories mc ON mc.mirror_id = m.id
            INNER JOIN categories c ON c.id = mc.category_id AND c.type = 'provider'
            LEFT JOIN mirror_status ms ON ms.mirror_id = m.id
            WHERE m.status = 'active' GROUP BY COALESCE(ms.status, 'unknown')");
        foreach ($stmt as $row) {
            $key = in_array($row['status'], ['up', 'slow', 'down'], true) ? $row['status'] : 'unknown';
            $out[$key] = (int)$row['c'];
        }

        $agg = $pdo->query("SELECT COUNT(*) AS total,
                    SUM(status IN ('up','slow')) AS ok,
                    AVG(CASE WHEN status IN ('up','slow') AND response_ms IS NOT NULL THEN response_ms END) AS avg_ms
                FROM mirror_checks WHERE checked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch();
        if ($agg && (int)$agg['total'] > 0) {
            $out['uptime_percent'] = round((int)$agg['ok'] * 100 / (int)$agg['total'], 2);
            $out['avg_response_ms'] = $agg['avg_ms'] !== null ? (int)round((float)$agg['avg_ms']) : null;
            $out['total_checks'] = (int)$agg['total'];
        }

        $out['open_incidents'] = (int)$pdo->query("SELECT COUNT(DISTINCT i.id) FROM mirror_incidents i
            INNER JOIN mirror_categories mc ON mc.mirror_id = i.mirror_id
            INNER JOIN categories c ON c.id = mc.category_id AND c.type = 'provider'
            WHERE i.ended_at IS NULL")->fetchColumn();

        $settings = monitorSettings($pdo);
        $out['last_run_at'] = $settings['last_run_at'] ?: null;
        $out['last_run_ms'] = (int)$settings['last_run_ms'];
        $out['last_run_checked'] = (int)$settings['last_run_checked'];
        $out['enabled'] = (int)$settings['enabled'] === 1;
        $out['interval_hours'] = (float)$settings['interval_hours'];
        $out['last_cron_run_at'] = $settings['last_cron_run_at'] ?: null;
        $out['previous_cron_run_at'] = $settings['previous_cron_run_at'] ?: null;
        if (!empty($out['last_cron_run_at']) && !empty($out['previous_cron_run_at'])) {
            $lastCronTs = strtotime((string)$out['last_cron_run_at']);
            $previousCronTs = strtotime((string)$out['previous_cron_run_at']);
            if ($lastCronTs !== false && $previousCronTs !== false && $lastCronTs > $previousCronTs) {
                $out['observed_cron_interval_seconds'] = $lastCronTs - $previousCronTs;
            }
        }
    } catch (Throwable $e) {
    }
    return $out;
}

/** وضعیت لحظه‌ای همهٔ میرورهای فعال (برای صفحهٔ وضعیت) */
function monitorMirrorRows(PDO $pdo): array {
    try {
        $stmt = $pdo->query("SELECT m.id, m.name_fa, m.name_en, m.slug, m.url,
                    COALESCE(ms.status, 'unknown') AS status,
                    ms.last_checked_at, ms.last_response_ms, ms.last_http_code, ms.last_error,
                    ms.down_since, ms.consecutive_failures, ms.uptime_percent, ms.avg_response_ms, ms.checks_total
                FROM mirrors m
                INNER JOIN mirror_categories mc ON mc.mirror_id = m.id
                INNER JOIN categories c ON c.id = mc.category_id AND c.type = 'provider'
                LEFT JOIN mirror_status ms ON ms.mirror_id = m.id
                WHERE m.status = 'active'
                GROUP BY m.id, m.name_fa, m.name_en, m.slug, m.url, ms.status, ms.last_checked_at, ms.last_response_ms, ms.last_http_code, ms.last_error, ms.down_since, ms.consecutive_failures, ms.uptime_percent, ms.avg_response_ms, ms.checks_total
                ORDER BY FIELD(COALESCE(ms.status, 'unknown'), 'down', 'slow', 'unknown', 'up'), m.name_fa ASC");
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/** وضعیت چند میرور مشخص با یک کوئری (برای کارت‌های سایت؛ بدون N+1) */
function monitorStatusMap(PDO $pdo, array $mirrorIds): array {
    $ids = array_values(array_unique(array_map('intval', $mirrorIds)));
    if (empty($ids)) return [];
    $in = implode(',', $ids);
    try {
        $stmt = $pdo->query("SELECT mirror_id, status, last_checked_at, last_response_ms, uptime_percent
            FROM mirror_status WHERE mirror_id IN ($in)");
        $map = [];
        foreach ($stmt as $row) {
            $map[(int)$row['mirror_id']] = $row;
        }
        return $map;
    } catch (Throwable $e) {
        return [];
    }
}

/** Fetch daily uptime series for multiple mirrors with one query. */
function monitorUptimeSeriesMap(PDO $pdo, array $mirrorIds, int $days = 30): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $mirrorIds), static fn($id) => $id > 0)));
    if (!$ids) return [];
    $days = max(1, min(3660, $days));
    $in = implode(',', $ids);
    $map = [];
    try {
        $stmt = $pdo->query("SELECT mirror_id, day, checks, up, slow, down, sum_ms
            FROM mirror_daily_stats
            WHERE mirror_id IN ($in) AND day >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
            ORDER BY mirror_id, day ASC");
        foreach ($stmt as $row) {
            $map[(int)$row['mirror_id']][$row['day']] = $row;
        }
    } catch (Throwable $e) {
        foreach ($ids as $id) $map[$id] = monitorUptimeSeries($pdo, $id, $days);
    }
    return $map;
}

/** Fetch the newest latency points for every mirror in a single query where window functions are supported. */
function monitorLatencySeriesMap(PDO $pdo, array $mirrorIds, int $points = 30): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $mirrorIds), static fn($id) => $id > 0)));
    if (!$ids) return [];
    $points = max(1, min(200, $points));
    $in = implode(',', $ids);
    $map = [];
    try {
        $stmt = $pdo->query("SELECT mirror_id, response_ms, status, checked_at
            FROM (
                SELECT mirror_id, response_ms, status, checked_at,
                       ROW_NUMBER() OVER (PARTITION BY mirror_id ORDER BY id DESC) AS row_num
                FROM mirror_checks
                WHERE mirror_id IN ($in) AND response_ms IS NOT NULL AND status IN ('up','slow')
            ) AS ranked_checks
            WHERE row_num <= $points
            ORDER BY mirror_id, checked_at ASC");
        foreach ($stmt as $row) {
            $map[(int)$row['mirror_id']][] = [
                'ms' => (int)$row['response_ms'],
                'status' => $row['status'],
                'at' => $row['checked_at'],
            ];
        }
    } catch (Throwable $e) {
        // Older MySQL/MariaDB versions fall back to the original compatible query.
        foreach ($ids as $id) $map[$id] = monitorLatencySeries($pdo, $id, $points);
    }
    return $map;
}

/** سری روزانهٔ آپتایم برای نوار ۳۰ روزه */
function monitorUptimeSeries(PDO $pdo, int $mirrorId, int $days = 30): array {
    try {
        $stmt = $pdo->prepare("SELECT day, checks, up, slow, down, sum_ms
            FROM mirror_daily_stats
            WHERE mirror_id = ? AND day >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ORDER BY day ASC");
        $stmt->execute([$mirrorId, $days]);
        $map = [];
        foreach ($stmt as $row) {
            $map[$row['day']] = $row;
        }
        return $map;
    } catch (Throwable $e) {
        return [];
    }
}

/** آخرین اندازه‌گیری‌های زمان پاسخ (برای نمودار روند) */
function monitorLatencySeries(PDO $pdo, int $mirrorId, int $points = 30): array {
    try {
        $stmt = $pdo->prepare("SELECT response_ms, status, checked_at FROM mirror_checks
            WHERE mirror_id = ? AND response_ms IS NOT NULL AND status IN ('up','slow')
            ORDER BY id DESC LIMIT ?");
        $stmt->execute([$mirrorId, $points]);
        $rows = array_reverse($stmt->fetchAll());
        return array_map(fn($r) => ['ms' => (int)$r['response_ms'], 'status' => $r['status'], 'at' => $r['checked_at']], $rows);
    } catch (Throwable $e) {
        return [];
    }
}

/** آخرین رخدادهای قطعی */
function monitorRecentIncidents(PDO $pdo, int $limit = 15): array {
    try {
        $limit = max(1, min(100, $limit));
        $stmt = $pdo->query("SELECT i.*, m.name_fa, m.name_en, m.url
            FROM mirror_incidents i
            INNER JOIN mirrors m ON m.id = i.mirror_id
            ORDER BY i.started_at DESC LIMIT $limit");
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/** حذف تاریخچهٔ یک میرور یا همه (از پنل مدیریت) */
function monitorPurgeHistory(PDO $pdo, ?int $mirrorId = null): void {
    if ($mirrorId !== null) {
        $pdo->prepare("DELETE FROM mirror_checks WHERE mirror_id = ?")->execute([$mirrorId]);
        $pdo->prepare("DELETE FROM mirror_daily_stats WHERE mirror_id = ?")->execute([$mirrorId]);
        $pdo->prepare("DELETE FROM mirror_status WHERE mirror_id = ?")->execute([$mirrorId]);
        return;
    }
    $pdo->exec("DELETE FROM mirror_checks");
    $pdo->exec("DELETE FROM mirror_daily_stats");
    $pdo->exec("DELETE FROM mirror_status");
}

/* ============================================================
   کمک‌تابع‌های نمایشی
   ============================================================ */

/** تبدیل ارقام به فارسی */
function monitorFa($value): string {
    return strtr((string)$value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
}

/** تاریخ/ساعت فارسی */
function monitorFaDateTime(?string $datetime): string {
    if (empty($datetime)) return '—';
    $ts = strtotime($datetime);
    return $ts ? monitorFa(jalaliFormatDate($ts, 'Y/m/d H:i', false)) : '—';
}

/** «۵ دقیقه پیش» */
function monitorTimeAgoFa(?string $datetime): string {
    if (empty($datetime)) return 'هرگز';
    $ts = strtotime($datetime);
    if (!$ts) return '—';
    $s = max(0, time() - $ts);
    if ($s < 60) return 'چند لحظه پیش';
    if ($s < 3600) return monitorFa((int)floor($s / 60)) . ' دقیقه پیش';
    if ($s < 86400) return monitorFa((int)floor($s / 3600)) . ' ساعت پیش';
    return monitorFa((int)floor($s / 86400)) . ' روز پیش';
}

/** «۲ ساعت و ۱۵ دقیقه» */
function monitorDurationFa(int $seconds): string {
    if ($seconds < 60) return monitorFa($seconds) . ' ثانیه';
    $parts = [];
    $h = (int)floor($seconds / 3600);
    $m = (int)floor(($seconds % 3600) / 60);
    if ($h > 0) $parts[] = monitorFa($h) . ' ساعت';
    if ($m > 0) $parts[] = monitorFa($m) . ' دقیقه';
    return implode(' و ', $parts) ?: monitorFa($seconds) . ' ثانیه';
}

/** نشان وضعیت (badge) با رنگ مربوطه */
function monitorStatusBadge(?array $row, bool $showMs = true): string {
    if (!$row) {
        return '<span class="mon-badge mon-unknown">' . icon('info', 13) . '<span>بررسی‌نشده</span></span>';
    }
    $status = $row['status'] ?? 'unknown';
    $label = monitorStatusFa($status);
    $ms = $row['last_response_ms'] ?? null;
    $html = '<span class="mon-badge mon-' . htmlspecialchars($status) . '">'
        . '<span class="mon-dot"></span><span>' . htmlspecialchars($label) . '</span>';
    if ($showMs && $ms !== null && in_array($status, ['up', 'slow'], true)) {
        $html .= '<span class="mon-ms">' . monitorFa((int)$ms) . ' ms</span>';
    }
    $html .= '</span>';
    return $html;
}

/** نشان کوچک وضعیت برای کارت‌های سایت؛ اگر چیزی ثبت نشده باشد خالی برمی‌گردد */
function monitorCardBadge(?array $row): string {
    if (!$row || ($row['status'] ?? 'unknown') === 'unknown') return '';
    $status = $row['status'];
    $title = 'وضعیت سرور: ' . monitorStatusFa($status)
        . ' — آخرین بررسی: ' . monitorTimeAgoFa($row['last_checked_at'] ?? null);
    if (!empty($row['uptime_percent'])) {
        $title .= ' — آپتایم ۳۰ روزه: ' . monitorFa(number_format((float)$row['uptime_percent'], 1)) . '٪';
    }
    $ms = $row['last_response_ms'] ?? null;
    $html = '<span class="mon-badge mon-sm mon-' . htmlspecialchars($status) . '" title="' . htmlspecialchars($title) . '">'
        . '<span class="mon-dot"></span>';
    if ($ms !== null && in_array($status, ['up', 'slow'], true)) {
        $html .= monitorFa((int)$ms) . ' ms';
    } else {
        $html .= monitorStatusFa($status);
    }
    $html .= '</span>';
    return $html;
}

/**
 * نوار آپتایم روزانه (مثل صفحهٔ وضعیت سرویس‌های بزرگ):
 * هر تکه یک روز است؛ سبز/زرد/قرمز/خاکستری.
 */
function monitorUptimeBar(array $daily, int $days = 30): string {
    $html = '<div class="uptime-bar" aria-label="آپتایم ' . monitorFa($days) . ' روز اخیر">';
    for ($i = $days - 1; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i day"));
        $row = $daily[$day] ?? null;
        $short = monitorFa(jalaliFormatDate($day, 'm/d', false));
        if (!$row || (int)$row['checks'] === 0) {
            $html .= '<span class="uptime-seg is-none" title="' . $short . ' — بدون بررسی"></span>';
            continue;
        }
        $total = (int)$row['checks'];
        $down = (int)$row['down'];
        $slow = (int)$row['slow'];
        $up = (int)$row['up'];
        $pct = round(($up + $slow) * 100 / $total, 1);
        $cls = $down > 0 ? 'is-down' : ($slow > 0 ? 'is-slow' : 'is-up');
        $title = $short . ' — آپتایم ' . monitorFa(number_format($pct, 1)) . '٪ (' . monitorFa($up) . ' سالم، '
            . monitorFa($slow) . ' کند، ' . monitorFa($down) . ' قطع از ' . monitorFa($total) . ' بررسی)';
        $html .= '<span class="uptime-seg ' . $cls . '" title="' . htmlspecialchars($title) . '"></span>';
    }
    $html .= '</div>';
    return $html;
}

/** نمودار روند زمان پاسخ (SVG سبک، بدون هیچ کتابخانهٔ جانبی) */
function monitorSparkline(array $points, int $width = 130, int $height = 34): string {
    if (count($points) < 2) return '';
    $values = array_map(fn($p) => (int)$p['ms'], $points);
    $max = max($values);
    $min = min($values);
    if ($max === $min) $max = $min + 1;
    $step = $width / (count($points) - 1);
    $coords = [];
    foreach ($values as $i => $v) {
        $x = round($i * $step, 1);
        $y = round($height - 3 - (($v - $min) / ($max - $min)) * ($height - 6), 1);
        $coords[] = $x . ',' . $y;
    }
    $failures = 0;
    foreach ($points as $p) if (($p['status'] ?? '') === 'down') $failures++;

    $avg = (int)round(array_sum($values) / count($values));
    $title = 'روند زمان پاسخ — ' . monitorFa(count($points)) . ' بررسی اخیر؛ کمینه ' . monitorFa($min)
        . '، بیشینه ' . monitorFa($max) . '، میانگین ' . monitorFa($avg) . ' میلی‌ثانیه';

    return '<svg class="sparkline" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" title="' . htmlspecialchars($title) . '">'
        . '<polyline fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" points="' . implode(' ', $coords) . '"/>'
        . '</svg>';
}
