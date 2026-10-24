<?php
require_once 'config.php';

$message = '';
$error = '';
$installed = false;
$showSetupForm = false;

if (!isset($_SESSION['mirrorhub_install_csrf']) || !is_string($_SESSION['mirrorhub_install_csrf'])) {
    $_SESSION['mirrorhub_install_csrf'] = bin2hex(random_bytes(32));
}
$installCsrfToken = $_SESSION['mirrorhub_install_csrf'];
$setupRequested = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && ($_POST['install_action'] ?? '') === 'install';

try {
    if (DB_NAME === '' || DB_USER === '' || DB_PASS === '') {
        throw new PDOException('Database credentials are not configured.');
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', DB_NAME)) {
        throw new PDOException('Invalid database name.');
    }

    $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");

    // توابع سامانهٔ پایش میرورها (ساخت جداول در انتها، پس از ساخت جداول اصلی)
    require_once __DIR__ . '/includes/monitoring.php';

    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->fetch()) {
        $error = "پایگاه داده قبلاً نصب شده است. نصب‌کننده فقط برای راه‌اندازی اولیه در دسترس است.";
    } elseif (!$setupRequested) {
        $showSetupForm = true;
    } else {
        $adminUsername = trim((string)($_POST['admin_username'] ?? ''));
        $adminPassword = (string)($_POST['admin_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $submittedToken = (string)($_POST['install_csrf_token'] ?? '');

        if (!hash_equals($installCsrfToken, $submittedToken)) {
            $error = 'درخواست نصب معتبر نیست. صفحه را تازه کنید و دوباره تلاش کنید.';
            $showSetupForm = true;
        } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $adminUsername)) {
            $error = 'نام کاربری باید ۳ تا ۵۰ نویسه و شامل حروف انگلیسی، عدد، نقطه، خط تیره یا زیرخط باشد.';
            $showSetupForm = true;
        } elseif (strlen($adminPassword) < 12) {
            $error = 'رمز عبور مدیر باید حداقل ۱۲ نویسه داشته باشد.';
            $showSetupForm = true;
        } elseif ($adminPassword !== $confirmPassword) {
            $error = 'تکرار رمز عبور با رمز اصلی یکسان نیست.';
            $showSetupForm = true;
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

        $pdo->exec("CREATE TABLE IF NOT EXISTS `request_links` (
            `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `request_id` INT NOT NULL,
            `url` VARCHAR(1000) NOT NULL,
            `sort_order` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_request_links_request` (`request_id`, `sort_order`),
            FOREIGN KEY (`request_id`) REFERENCES `requests`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

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

        $adminPassHash = password_hash($adminPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO `users` (`username`, `password_hash`, `role`) VALUES (?, ?, 'admin')");
        $stmt->execute([$adminUsername, $adminPassHash]);

        $stmt = $pdo->prepare("INSERT INTO `categories` (`name`, `slug`, `description`, `type`, `sort_order`) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['دسته‌بندی عمومی', 'general', 'دسته‌بندی پیش‌فرض برای میرورها', 'software', 1]);

        $installed = true;
        $message = "نصب با موفقیت انجام شد. حساب مدیر ساخته شد: " . $adminUsername;
        unset($_SESSION['mirrorhub_install_csrf']);
        }
    }

    if ($installed) {
        // جداول سامانهٔ پایش میرورها فقط پس از تأیید و اجرای نصب ساخته می‌شوند.
        ensureMonitorSchema($pdo);
    }
} catch (PDOException $e) {
    $error = ENVIRONMENT === 'development'
        ? "خطا در اتصال یا اجرای کوئری‌ها: " . $e->getMessage()
        : "اتصال به پایگاه داده یا اجرای نصب ناموفق بود. تنظیمات پایگاه داده را بررسی کنید.";
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب MirrorHub</title>
    <!-- نصب‌کننده مستقل است و برای نمایش به CDN خارجی وابسته نیست. -->
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
            font-family: Tahoma, sans-serif;
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
            <p class="info"><?= htmlspecialchars($message) ?></p>
            <a href="admin/login.php" class="btn">ورود به پنل مدیریت</a>
            <p class="info">پس از نصب موفق، فایل install.php را از مسیر عمومی حذف کنید یا دسترسی مستقیم به آن را در سرور مسدود کنید.</p>
        <?php elseif ($showSetupForm): ?>
            <?php if ($error): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <p class="info">برای ایجاد حساب مدیر، یک نام کاربری و رمز عبور منحصربه‌فرد انتخاب کنید. رمز عبور حداقل ۱۲ نویسه باشد.</p>
            <form method="post" action="install.php" autocomplete="off">
                <input type="hidden" name="install_csrf_token" value="<?= htmlspecialchars($installCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group">
                    <label for="admin_username">نام کاربری مدیر</label>
                    <input type="text" id="admin_username" name="admin_username" minlength="3" maxlength="50" pattern="[A-Za-z0-9_.-]{3,50}" required autocomplete="username" dir="ltr">
                </div>
                <div class="form-group">
                    <label for="admin_password">رمز عبور مدیر</label>
                    <input type="password" id="admin_password" name="admin_password" minlength="12" required autocomplete="new-password" dir="ltr">
                </div>
                <div class="form-group">
                    <label for="confirm_password">تکرار رمز عبور</label>
                    <input type="password" id="confirm_password" name="confirm_password" minlength="12" required autocomplete="new-password" dir="ltr">
                </div>
                <button type="submit" class="btn" name="install_action" value="install">نصب و ساخت حساب مدیر</button>
            </form>
        <?php elseif ($error): ?>
            <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <p class="info">اگر نصب قبلاً انجام شده، فایل install.php را از مسیر عمومی حذف کنید.</p>
        <?php else: ?>
            <div class="error">خطای نامشخص در هنگام نصب.</div>
        <?php endif; ?>
    </div>
</body>
</html>