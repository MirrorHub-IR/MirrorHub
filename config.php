<?php
/* Database credentials belong in environment variables or config.local.php. */
$localConfig = [];
$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    try {
        $candidateConfig = require $localConfigPath;
        if (is_array($candidateConfig)) {
            $localConfig = $candidateConfig;
        } else {
            error_log('MirrorHub: config.local.php must return an array.');
        }
    } catch (Throwable $configError) {
        error_log('MirrorHub: could not load config.local.php.');
    }
}

$getSetting = static function (string $key, string $environmentName, string $default = '') use ($localConfig): string {
    $environmentValue = getenv($environmentName);
    if (is_string($environmentValue) && $environmentValue !== '') {
        return $environmentValue;
    }
    $localValue = $localConfig[$key] ?? null;
    if (is_string($localValue) && $localValue !== '') {
        return $localValue;
    }
    return $default;
};

define('DB_HOST', $getSetting('DB_HOST', 'MIRRORHUB_DB_HOST', 'localhost'));
define('DB_NAME', $getSetting('DB_NAME', 'MIRRORHUB_DB_NAME'));
define('DB_USER', $getSetting('DB_USER', 'MIRRORHUB_DB_USER'));
define('DB_PASS', $getSetting('DB_PASS', 'MIRRORHUB_DB_PASS'));
define('DB_CHARSET', $getSetting('DB_CHARSET', 'MIRRORHUB_DB_CHARSET', 'utf8mb4'));
/* ============================================================
   Site Configuration
   ============================================================ */
define('SITE_NAME', 'MirrorHub');
define('SITE_URL', $getSetting('SITE_URL', 'MIRRORHUB_SITE_URL', 'https://mirrorhub.ir/beta'));
define('UPLOAD_DIR', __DIR__ . '/uploads/');

/* ============================================================
   Security
   ============================================================ */
define('CSRF_TOKEN_NAME', 'csrf_token');

/* ============================================================
   Environment
   ============================================================ */
define('ENVIRONMENT', $getSetting('ENVIRONMENT', 'MIRRORHUB_ENVIRONMENT', 'production'));

/* ============================================================
   Monitoring (سامانهٔ پایش میرورها)
   کلید دسترسی endpoint زمان‌بند وب (cron.php?key=...).
   خالی بگذارید تا خودکار یک کلید تصادفی ساخته و در
   جدول monitor_settings ذخیره شود (قابل مشاهده در پنل مدیریت).
   ============================================================ */
define('MONITOR_CRON_KEY', $getSetting('MONITOR_CRON_KEY', 'MIRRORHUB_MONITOR_CRON_KEY'));

/* ============================================================
   Timezone
   ============================================================ */
date_default_timezone_set('Asia/Tehran');

/* ============================================================
   Error Reporting
   ============================================================ */
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

/* ============================================================
   Session
   ============================================================ */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}