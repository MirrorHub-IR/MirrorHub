<?php
require_once 'config.php';

$message = '';
$error = '';
$installed = false;

try {
    $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");

    // توابع سامانهٔ پایش میرورها (ساخت جداول در انتها، پس از ساخت جداول اصلی)
    require_once __DIR__ . '/includes/monitoring.php';

    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->fetch()) {
        $error = "پایگاه داده قبلاً نصب شده است. در صورت نیاز به نصب مجدد، جداول را به‌صورت دستی حذف کنید.";
    } else {
        $pdo->exec("
            CREATE TABLE `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) NOT NULL UNIQUE,
                `password_hash` VARCHAR(255) NOT NULL,
                `role` ENUM('admin', 'user') DEFAULT 'user',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE `categories` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `slug` VARCHAR(100) NOT NULL UNIQUE,
                `description` TEXT,
                `parent_id` INT DEFAULT NULL,
                `type` ENUM('software', 'provider') NOT NULL,
                `icon` VARCHAR(255) DEFAULT NULL,
                `sort_order` INT DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
                INDEX `idx_type` (`type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE `mirrors` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name_fa` VARCHAR(120) NOT NULL,
                `name_en` VARCHAR(120) NOT NULL,
                `slug` VARCHAR(120) NOT NULL UNIQUE,
                `url` VARCHAR(1000) NOT NULL,
                `protocol` VARCHAR(32) DEFAULT 'https',
                `description` TEXT,
                `status` ENUM('active', 'inactive') DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE `mirror_categories` (
                `mirror_id` INT NOT NULL,
                `category_id` INT NOT NULL,
                PRIMARY KEY (`mirror_id`, `category_id`),
                FOREIGN KEY (`mirror_id`) REFERENCES `mirrors`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE `requests` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name_fa` VARCHAR(120) NOT NULL,
                `name_en` VARCHAR(120) DEFAULT NULL,
                `url` VARCHAR(1000) NOT NULL,
                `protocol` VARCHAR(32) DEFAULT 'https',
                `category_name` VARCHAR(120),
                `parent_category_id` INT DEFAULT NULL,
                `category_id` INT DEFAULT NULL,
                `description` TEXT,
                `status` ENUM('pending', 'reviewed') DEFAULT 'pending',
                `admin_note` TEXT,
                `mirror_id` INT DEFAULT NULL,
                `reviewed_by` INT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `reviewed_at` TIMESTAMP NULL,
                `email` VARCHAR(190) DEFAULT NULL,
                `phone` VARCHAR(30) DEFAULT NULL,
                INDEX `idx_requests_parent_category` (`parent_category_id`),
                INDEX `idx_requests_category` (`category_id`),
                FOREIGN KEY (`parent_category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
                FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `mirror_protocols` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `mirror_id` INT NOT NULL,
                `protocol` VARCHAR(32) NOT NULL,
                `custom_label` VARCHAR(100) DEFAULT NULL,
                `sort_order` INT DEFAULT 0,
                UNIQUE KEY `uq_mirror_protocol` (`mirror_id`, `protocol`, `custom_label`),
                FOREIGN KEY (`mirror_id`) REFERENCES `mirrors`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `request_protocols` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `request_id` INT NOT NULL,
                `protocol` VARCHAR(32) NOT NULL,
                `custom_label` VARCHAR(100) DEFAULT NULL,
                `sort_order` INT DEFAULT 0,
                UNIQUE KEY `uq_request_protocol` (`request_id`, `protocol`, `custom_label`),
                FOREIGN KEY (`request_id`) REFERENCES `requests`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("
            CREATE TABLE `visits` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `ip` VARCHAR(45) NOT NULL,
                `user_agent` VARCHAR(255) DEFAULT NULL,
                `page` VARCHAR(255) DEFAULT NULL,
                `visit_date` DATE NOT NULL,
                `visited_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_visit_date` (`visit_date`),
                INDEX `idx_ip_date` (`ip`, `visit_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO `users` (`username`, `password_hash`, `role`) VALUES (?, ?, 'admin')");
        $stmt->execute(['admin', $adminPass]);

        $stmt = $pdo->prepare("INSERT INTO `categories` (`name`, `slug`, `description`, `type`, `sort_order`) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['دسته‌بندی عمومی', 'general', 'دسته‌بندی پیش‌فرض برای میرورها', 'software', 1]);

        $installed = true;
        $message = "نصب با موفقیت انجام شد!";
    }

    // جداول سامانهٔ پایش میرورها (ایمن در برابر اجرای چندباره)
    ensureMonitorSchema($pdo);
} catch (PDOException $e) {
    $error = "خطا در اتصال یا اجرای کوئری‌ها: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب MirrorHub</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&display=swap">
    <style>
        :root {
            --bg-primary: #0e1410;
            --bg-secondary: #161d18;
            --bg-tertiary: #1d2620;
            --border-color: #2c3a31;
            --text-primary: #eaf3e8;
            --text-secondary: #9aae9c;
            --text-muted: #6f8371;
            --accent: #a3d65c;
            --accent-hover: #bde87a;
            --danger: #ec6b6b;
            --radius: 12px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Vazirmatn', Tahoma, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 2rem 1rem;
            line-height: 1.75;
        }
        .box {
            background: var(--bg-secondary);
            padding: 2.25rem 1.75rem;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            text-align: center;
            max-width: 440px;
            width: 100%;
        }
        h1 {
            margin-bottom: 1.5rem;
            font-size: 1.35rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
        }
        h1::before {
            content: '';
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--accent);
        }
        .success, .error {
            padding: 0.9rem 1.1rem;
            border-radius: 8px;
            margin: 0.75rem 0;
            font-weight: 500;
            font-size: 0.92rem;
            text-align: right;
        }
        .success {
            color: var(--accent-hover);
            background: rgba(163, 214, 92, 0.08);
            border: 1px solid rgba(163, 214, 92, 0.3);
        }
        .error {
            color: #ff9a9a;
            background: rgba(236, 107, 107, 0.08);
            border: 1px solid rgba(236, 107, 107, 0.3);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.7rem 1.5rem;
            background: var(--accent);
            color: #0e1410;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 1rem;
            transition: all 0.2s ease;
            font-size: 0.92rem;
        }
        .btn:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
        }
        .info {
            font-size: 0.82rem;
            color: var(--text-muted);
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
            line-height: 1.8;
        }
        .credentials {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.9rem 1.1rem;
            margin: 0.9rem 0;
            text-align: right;
        }
        .credentials p { margin: 0.3rem 0; color: var(--text-secondary); font-size: 0.88rem; }
        .credentials strong { color: var(--accent); font-family: 'Consolas', monospace; direction: ltr; display: inline-block; }
    </style>
</head>
<body>
    <div class="box">
        <h1>نصب MirrorHub</h1>
        <?php if ($installed): ?>
            <div class="success"><?= htmlspecialchars($message) ?></div>
            <div class="credentials">
                <p>نام کاربری مدیر: <strong>admin</strong></p>
                <p>رمز عبور مدیر: <strong>admin123</strong></p>
            </div>
            <a href="admin/login.php" class="btn">ورود به پنل مدیریت</a>
            <p class="info">لطفاً پس از ورود، رمز عبور را تغییر دهید و این فایل را حذف کنید.</p>
        <?php elseif ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php else: ?>
            <div class="error">خطای نامشخص در هنگام نصب.</div>
        <?php endif; ?>
    </div>
</body>
</html>