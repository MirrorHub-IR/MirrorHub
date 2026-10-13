<?php
/* ============================================================
   Database Configuration
   ============================================================ */
define('DB_HOST', 'localhost');
define('DB_NAME', 'laospixr_mirrorhub');
define('DB_USER', 'laospixr_mirrorhub');
define('DB_PASS', 'laospixr_mirrorhub');
define('DB_CHARSET', 'utf8mb4');

/* ============================================================
   Site Configuration
   ============================================================ */
define('SITE_NAME', 'MirrorHub');
define('SITE_URL', 'https://mirrorhub.ir/beta');
define('UPLOAD_DIR', __DIR__ . '/uploads/');

/* ============================================================
   Security
   ============================================================ */
define('CSRF_TOKEN_NAME', 'csrf_token');

/* ============================================================
   Environment
   ============================================================ */
define('ENVIRONMENT', 'production');

/* ============================================================
   Monitoring (سامانهٔ پایش میرورها)
   کلید دسترسی endpoint زمان‌بند وب (cron.php?key=...).
   خالی بگذارید تا خودکار یک کلید تصادفی ساخته و در
   جدول monitor_settings ذخیره شود (قابل مشاهده در پنل مدیریت).
   ============================================================ */
define('MONITOR_CRON_KEY', '');

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