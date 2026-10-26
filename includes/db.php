<?php
require_once __DIR__ . '/../config.php';

if (DB_NAME === '' || DB_USER === '' || DB_PASS === '') {
    require_once __DIR__ . '/installation.php';
    $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $state = mirrorhubInstallationStatus();
    if (in_array($scriptName, ['api.php', 'cron.php'], true) || PHP_SAPI === 'cli') {
        http_response_code(503);
        if ($scriptName === 'api.php') {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'installation_required', 'message' => 'MirrorHub is not configured yet. Open setup.php to install it.']);
        } else {
            echo "MirrorHub database is not configured. Run setup.php first.\\n";
        }
        exit;
    }
    if (in_array($state, ['not_installed', 'incomplete'], true)) {
        header('Location: ' . mirrorhubSetupUrl(), true, 302);
        exit;
    }
    http_response_code(503);
    die('تنظیمات اتصال دیتابیس موجود نیست یا نصب قبلی به تنظیمات معتبر دسترسی ندارد. فایل تنظیمات محلی و دسترسی‌های استقرار را بررسی کنید.');
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    // Stop application routes while a first-time installation is incomplete.
    $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $tableCheck = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $tableCheck->execute(['mirrorhub_installation']);
    $hasInstallState = (int)$tableCheck->fetchColumn() > 0;
    $state = $hasInstallState ? $pdo->query('SELECT status FROM mirrorhub_installation WHERE id = 1')->fetchColumn() : null;
    $tableCheck->execute(['users']);
    $hasUsers = (int)$tableCheck->fetchColumn() > 0;
    if (($hasInstallState && $state !== 'complete') || (!$hasInstallState && !$hasUsers)) {
        if (in_array($scriptName, ['api.php', 'cron.php'], true) || PHP_SAPI === 'cli') {
            http_response_code(503);
            if ($scriptName === 'api.php') {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['error' => 'installation_incomplete', 'message' => 'MirrorHub setup has not completed.']);
            } else {
                echo "MirrorHub setup has not completed.\\n";
            }
            exit;
        }
        require_once __DIR__ . '/installation.php';
        header('Location: ' . mirrorhubSetupUrl(), true, 302);
        exit;
    }

    // Self-healing database migration for categories columns
    $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($scriptName !== 'setup.php') {
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
        if ($scriptName !== 'setup.php') {
            ensureMonitorSchema($pdo);
            // Avoid fallback monitoring cycles from scheduler bootstrap or crawler metadata endpoints.
            if (!in_array($scriptName, ['cron.php', 'sitemap.php'], true)) {
                monitorMaybeRunDaily($pdo);
            }
        }
        require_once __DIR__ . '/cms.php';
        if ($scriptName !== 'setup.php') {
            ensureCmsSchema($pdo);
        }
    }
} catch (\PDOException $e) {
    $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($scriptName !== 'setup.php') {
        http_response_code(500);
        if (ENVIRONMENT === 'development') {
            die("خطا در اتصال به پایگاه داده: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
        die("خطا در اتصال به پایگاه داده. تنظیمات اتصال و دسترسی پایگاه داده را بررسی کنید.");
    }
}