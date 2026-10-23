<?php
require_once __DIR__ . '/../config.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    // Self-healing database migration for categories columns
    $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($scriptName !== 'install.php') {
        try {
            $checkStmt = $pdo->query("SHOW COLUMNS FROM `categories` LIKE 'type'");
            if (!$checkStmt->fetch()) {
                // 'type' column is missing, run migration automatically
                $pdo->exec("ALTER TABLE `categories` ADD COLUMN `type` ENUM('software', 'provider') NOT NULL DEFAULT 'software' AFTER `parent_id`");
                $pdo->exec("UPDATE `categories` SET `type` = 'software' WHERE `type` IS NULL OR `type` = ''");
                
                // Also check and add 'icon' if missing
                $iconCheck = $pdo->query("SHOW COLUMNS FROM `categories` LIKE 'icon'");
                if (!$iconCheck->fetch()) {
                    $pdo->exec("ALTER TABLE `categories` ADD COLUMN `icon` VARCHAR(255) DEFAULT NULL AFTER `type`");
                }
                
                // Add index on 'type' if missing
                $indexCheck = $pdo->query("SHOW INDEX FROM `categories` WHERE Key_name = 'idx_type'");
                if (!$indexCheck->fetch()) {
                    $pdo->exec("ALTER TABLE `categories` ADD INDEX `idx_type` (`type`)");
                }
            }
        } catch (\Exception $migrationError) {
            // Silently ignore or let it log if needed, don't block main execution
        }

        /* --------------------------------------------------------
           سامانهٔ پایش میرورها:
           ۱) جداول پایش در صورت نبود ساخته می‌شوند (خودترمیم).
           ۲) اگر چرخهٔ روزانهٔ پایش دیر شده باشد، همین‌جا کلید
              می‌خورد (اجرای پس‌زمینه یا محدود)؛ جزئیات:
              includes/monitoring.php → monitorMaybeRunDaily
           -------------------------------------------------------- */
        require_once __DIR__ . '/monitoring.php';
        if ($scriptName !== 'install.php') {
            ensureMonitorSchema($pdo);
            // Avoid starting a second fallback cycle while cron.php is bootstrapping.
            if ($scriptName !== 'cron.php') {
                monitorMaybeRunDaily($pdo);
            }
        }
        require_once __DIR__ . '/cms.php';
        if ($scriptName !== 'install.php') {
            ensureCmsSchema($pdo);
        }
    }
} catch (\PDOException $e) {
    $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($scriptName !== 'install.php') {
        http_response_code(500);
        if (ENVIRONMENT === 'development') {
            die("خطا در اتصال به پایگاه داده: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
        die("خطا در اتصال به پایگاه داده. لطفاً ابتدا فایل install.php را اجرا کنید.");
    }
}