<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/installation.php';

$installationStatus = mirrorhubInstallationStatus();
if ($installationStatus === 'installed') {
    $destination = mirrorhubBasePath() . (($_SESSION['role'] ?? '') === 'admin' && isset($_SESSION['user_id']) ? '/admin/dashboard.php' : '/admin/login.php');
    header('Location: ' . $destination, true, 302);
    exit;
}

if (!isset($_SESSION['mirrorhub_setup_csrf']) || !is_string($_SESSION['mirrorhub_setup_csrf'])) {
    $_SESSION['mirrorhub_setup_csrf'] = bin2hex(random_bytes(32));
}
$setupCsrfToken = $_SESSION['mirrorhub_setup_csrf'];

function mirrorhubSetupDatabaseFields(array $input): array
{
    return [
        'host' => trim((string)($input['db_host'] ?? 'localhost')),
        'port' => trim((string)($input['db_port'] ?? '3306')),
        'name' => trim((string)($input['db_name'] ?? '')),
        'user' => trim((string)($input['db_user'] ?? '')),
        'pass' => (string)($input['db_pass'] ?? ''),
    ];
}

function mirrorhubSetupValidateDatabase(array $db): array
{
    $errors = [];
    $hostIsValid = preg_match('/^[A-Za-z0-9][A-Za-z0-9.-]*$/', $db['host']) === 1 || filter_var($db['host'], FILTER_VALIDATE_IP) !== false;
    if (!$hostIsValid) {
        $errors[] = 'میزبان دیتابیس معتبر نیست.';
    }
    if (!ctype_digit($db['port']) || (int)$db['port'] < 1 || (int)$db['port'] > 65535) {
        $errors[] = 'پورت دیتابیس باید عددی بین ۱ تا ۶۵۵۳۵ باشد.';
    }
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $db['name'])) {
        $errors[] = 'نام دیتابیس باید ۱ تا ۶۴ نویسه و فقط شامل حروف انگلیسی، عدد و زیرخط باشد.';
    }
    if ($db['user'] === '' || strlen($db['user']) > 128 || str_contains($db['user'], chr(0))) {
        $errors[] = 'نام کاربری دیتابیس معتبر نیست.';
    }
    if ($db['pass'] === '' || str_contains($db['pass'], chr(0))) {
        $errors[] = 'رمز عبور دیتابیس را وارد کنید.';
    }
    return $errors;
}

function mirrorhubSetupPdo(array $db, bool $createDatabase): array
{
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $serverDsn = 'mysql:host=' . $db['host'] . ';port=' . (int)$db['port'] . ';charset=utf8mb4';
    $databaseDsn = $serverDsn . ';dbname=' . $db['name'];

    try {
        return ['pdo' => new PDO($databaseDsn, $db['user'], $db['pass'], $options), 'server_only' => false];
    } catch (Throwable $databaseError) {
        try {
            $serverPdo = new PDO($serverDsn, $db['user'], $db['pass'], $options);
        } catch (Throwable $serverError) {
            throw new RuntimeException('اتصال به سرور دیتابیس برقرار نشد. میزبان، پورت، نام کاربری و رمز عبور را بررسی کنید.');
        }
        if (!$createDatabase) {
            return ['pdo' => null, 'server_only' => true];
        }
        try {
            $quotedDatabase = chr(96) . $db['name'] . chr(96);
            $serverPdo->exec('CREATE DATABASE IF NOT EXISTS ' . $quotedDatabase . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            return ['pdo' => new PDO($databaseDsn, $db['user'], $db['pass'], $options), 'server_only' => false];
        } catch (Throwable $createError) {
            throw new RuntimeException('به سرور متصل شدیم، اما دیتابیس قابل دسترسی نیست و مجوز ساخت آن هم تأیید نشد. دیتابیس را از پنل هاست ایجاد کنید و دسترسی ساخت جدول را به کاربر آن بدهید.');
        }
    }
}

function mirrorhubSetupEnvironmentConflicts(array $db, string $siteUrl): array
{
    $settings = [
        'host' => 'MIRRORHUB_DB_HOST',
        'port' => 'MIRRORHUB_DB_PORT',
        'name' => 'MIRRORHUB_DB_NAME',
        'user' => 'MIRRORHUB_DB_USER',
        'pass' => 'MIRRORHUB_DB_PASS',
        'site' => 'MIRRORHUB_SITE_URL',
    ];
    $values = $db;
    $values['site'] = $siteUrl;
    $errors = [];
    foreach ($settings as $key => $environmentName) {
        $environmentValue = getenv($environmentName);
        if (is_string($environmentValue) && $environmentValue !== '') {
            $candidate = $key === 'site' ? rtrim($values[$key], '/') : (string)$values[$key];
            $environmentValue = $key === 'site' ? rtrim($environmentValue, '/') : $environmentValue;
            if ($candidate !== $environmentValue) {
                $errors[] = 'متغیر محیطی ' . $environmentName . ' اولویت دارد و با مقدار واردشده یکسان نیست؛ تنظیمات محیط هاست را هماهنگ کنید.';
            }
        }
    }
    return $errors;
}

function mirrorhubSetupValidateSiteUrl(string $siteUrl): bool
{
    if ($siteUrl === '' || filter_var($siteUrl, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    $parts = parse_url($siteUrl);
    return is_array($parts)
        && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
        && !empty($parts['host'])
        && !isset($parts['user'])
        && !isset($parts['pass'])
        && !isset($parts['query'])
        && !isset($parts['fragment'])
        && !str_contains($siteUrl, chr(0));
}

function mirrorhubSetupWriteConfig(array $values): void
{
    $configPath = __DIR__ . '/config.local.php';
    if (is_link($configPath)) {
        throw new RuntimeException('فایل تنظیمات محلی یک پیوند نمادین است؛ پیش از نصب آن را به‌صورت امن بررسی کنید.');
    }
    $config = [
        'DB_HOST' => $values['host'],
        'DB_PORT' => (string)(int)$values['port'],
        'DB_NAME' => $values['name'],
        'DB_USER' => $values['user'],
        'DB_PASS' => $values['pass'],
        'DB_CHARSET' => 'utf8mb4',
        'SITE_URL' => rtrim($values['site_url'], '/'),
        'ENVIRONMENT' => 'production',
        'MONITOR_CRON_KEY' => '',
    ];
    $content = "<?php\n// Generated by MirrorHub Setup. Keep this file private and out of version control.\nreturn " . var_export($config, true) . ";\n";
    $temporaryPath = __DIR__ . '/.config.local.php.tmp.' . bin2hex(random_bytes(6));
    if (file_put_contents($temporaryPath, $content, LOCK_EX) === false) {
        @unlink($temporaryPath);
        throw new RuntimeException('ذخیره تنظیمات دیتابیس ممکن نشد. مجوز نوشتن محدود برای config.local.php را بررسی کنید.');
    }
    @chmod($temporaryPath, 0640);
    if (!@rename($temporaryPath, $configPath)) {
        @unlink($temporaryPath);
        throw new RuntimeException('جایگزینی امن فایل تنظیمات ممکن نشد؛ دسترسی نوشتن پوشه را بررسی کنید.');
    }
    @chmod($configPath, 0640);
}

function mirrorhubSetupJsonResponse(bool $ok, string $message, string $kind = 'result'): void
{
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode(['ok' => $ok, 'message' => $message, 'kind' => $kind], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$posted = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$action = (string)($_POST['setup_action'] ?? '');
$dbFields = mirrorhubSetupDatabaseFields($posted ? $_POST : []);
$siteUrl = trim((string)($_POST['site_url'] ?? ''));
$adminUsername = trim((string)($_POST['admin_username'] ?? ''));
$adminPassword = (string)($_POST['admin_password'] ?? '');
$confirmPassword = (string)($_POST['confirm_password'] ?? '');
$errors = [];

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$httpHost = (string)($_SERVER['HTTP_HOST'] ?? '');
if ($httpHost !== '' && preg_match('/^[A-Za-z0-9.:-]+$/', $httpHost)) {
    $guessedSiteUrl = $https . '://' . $httpHost . mirrorhubBasePath();
} else {
    $guessedSiteUrl = '';
}
$defaultSiteUrl = DB_NAME !== '' && DB_USER !== '' && SITE_URL !== '' ? SITE_URL : $guessedSiteUrl;
if ($siteUrl === '') {
    $siteUrl = $defaultSiteUrl;
}
if (!$posted) {
    $dbFields['host'] = DB_HOST;
    $dbFields['port'] = defined('DB_PORT') ? (string)DB_PORT : '3306';
    $dbFields['name'] = DB_NAME;
    $dbFields['user'] = DB_USER;
}

if ($posted && $action === 'test_connection') {
    if (!class_exists(PDO::class) || !extension_loaded('pdo_mysql')) {
        mirrorhubSetupJsonResponse(false, 'افزونه PDO MySQL روی PHP فعال نیست. ابتدا pdo_mysql را در تنظیمات PHP هاست فعال کنید.', 'error');
    }
    if (!hash_equals($setupCsrfToken, (string)($_POST['setup_csrf_token'] ?? ''))) {
        mirrorhubSetupJsonResponse(false, 'توکن امنیتی منقضی یا نامعتبر است. صفحه را تازه کنید.', 'error');
    }
    $dbErrors = mirrorhubSetupValidateDatabase($dbFields);
    if ($dbErrors) {
        mirrorhubSetupJsonResponse(false, implode(' ', $dbErrors), 'error');
    }
    try {
        $probe = mirrorhubSetupPdo($dbFields, false);
        if ($probe['pdo'] instanceof PDO) {
            mirrorhubSetupJsonResponse(true, 'اتصال به دیتابیس برقرار شد. در مرحله نصب، ساختار جدول‌ها نیز بررسی می‌شود.', 'success');
        }
        mirrorhubSetupJsonResponse(false, 'ورود به سرور موفق است، اما دیتابیس هنوز قابل اتصال نیست. هنگام نصب، ایجاد آن امتحان می‌شود؛ ممکن است به مجوز CREATE DATABASE یا ساخت دستی از پنل هاست نیاز باشد.', 'warning');
    } catch (Throwable $error) {
        mirrorhubSetupJsonResponse(false, $error->getMessage(), 'error');
    }
}

if ($posted && $action === 'install') {
    if (PHP_VERSION_ID < 80200) {
        $errors[] = 'MirrorHub به PHP نسخه 8.2 یا جدیدتر نیاز دارد.';
    }
    if (!class_exists(PDO::class) || !extension_loaded('pdo_mysql')) {
        $errors[] = 'افزونه PDO MySQL فعال نیست؛ pdo_mysql را در تنظیمات PHP هاست فعال کنید.';
    }
    if (!extension_loaded('mbstring')) {
        $errors[] = 'افزونه mbstring فعال نیست؛ پیش از نصب آن را در PHP فعال کنید.';
    }
    if (!extension_loaded('fileinfo')) {
        $errors[] = 'افزونه fileinfo فعال نیست؛ پیش از نصب آن را در PHP فعال کنید.';
    }
    if (!extension_loaded('curl')) {
        $errors[] = 'افزونه curl فعال نیست؛ پیش از نصب آن را در PHP فعال کنید.';
    }
    if (!is_writable(__DIR__)) {
        $errors[] = 'پوشه اصلی برنامه قابل نوشتن نیست؛ مجوز محدود لازم برای ساخت فایل تنظیمات را فراهم کنید.';
    }
    if (!hash_equals($setupCsrfToken, (string)($_POST['setup_csrf_token'] ?? ''))) {
        $errors[] = 'درخواست امنیتی معتبر نیست. صفحه را تازه کنید و دوباره تلاش کنید.';
    }
    $errors = array_merge($errors, mirrorhubSetupValidateDatabase($dbFields));
    if (!mirrorhubSetupValidateSiteUrl($siteUrl)) {
        $errors[] = 'آدرس سایت باید یک URL کامل با http یا https و بدون query یا fragment باشد.';
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $adminUsername)) {
        $errors[] = 'نام کاربری مدیر باید ۳ تا ۵۰ نویسه و شامل حروف انگلیسی، عدد، نقطه، خط تیره یا زیرخط باشد.';
    }
    if (!function_exists('mb_strlen') || mb_strlen($adminPassword, 'UTF-8') < 12) {
        $errors[] = 'رمز عبور مدیر باید حداقل ۱۲ نویسه داشته باشد.';
    }
    if ($adminPassword !== $confirmPassword) {
        $errors[] = 'تکرار رمز عبور با رمز اصلی یکسان نیست.';
    }
    $errors = array_merge($errors, mirrorhubSetupEnvironmentConflicts($dbFields, $siteUrl));

    if (!$errors) {
        $step = 'اتصال دیتابیس';
        try {
            $probe = mirrorhubSetupPdo($dbFields, true);
            $pdo = $probe['pdo'];
            if (!$pdo instanceof PDO) {
                throw new RuntimeException('اتصال به دیتابیس برقرار نشد.');
            }

            $existingTables = array_map('strval', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
            $setupTableExists = in_array('mirrorhub_installation', $existingTables, true);
            if ($setupTableExists) {
                $setupState = $pdo->query('SELECT status FROM mirrorhub_installation WHERE id = 1')->fetchColumn();
                if ($setupState === 'complete') {
                    throw new RuntimeException('این دیتابیس قبلاً نصب شده است و برای جلوگیری از بازنویسی داده‌ها، نصب دوباره متوقف شد.');
                }
                if ($setupState !== 'installing') {
                    throw new RuntimeException('وضعیت نصب دیتابیس قابل تشخیص نیست. برای جلوگیری از تخریب داده‌ها، نصب متوقف شد.');
                }
            } elseif (count($existingTables) > 0) {
                throw new RuntimeException('این دیتابیس خالی نیست و سابقه نصب قابل ادامه ندارد. دیتابیس خالی انتخاب کنید یا اتصال نصب فعلی را بازیابی کنید.');
            }

            $step = 'ذخیره امن تنظیمات';
            mirrorhubSetupWriteConfig([
                'host' => $dbFields['host'],
                'port' => $dbFields['port'],
                'name' => $dbFields['name'],
                'user' => $dbFields['user'],
                'pass' => $dbFields['pass'],
                'site_url' => $siteUrl,
            ]);

            $step = 'ثبت وضعیت نصب';
            $pdo->exec("CREATE TABLE IF NOT EXISTS mirrorhub_installation (
                id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                status ENUM('installing','complete') NOT NULL DEFAULT 'installing',
                schema_version INT UNSIGNED NOT NULL DEFAULT 1,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("INSERT IGNORE INTO mirrorhub_installation (id, status, schema_version) VALUES (1, 'installing', 1)");

            $step = 'ساخت جدول‌های اصلی';
            $pdo->exec("
            CREATE TABLE IF NOT EXISTS `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) NOT NULL UNIQUE,
                `password_hash` VARCHAR(255) NOT NULL,
                `role` ENUM('admin', 'user') DEFAULT 'user',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `categories` (
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
            CREATE TABLE IF NOT EXISTS `mirrors` (
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
            CREATE TABLE IF NOT EXISTS `mirror_categories` (
                `mirror_id` INT NOT NULL,
                `category_id` INT NOT NULL,
                PRIMARY KEY (`mirror_id`, `category_id`),
                FOREIGN KEY (`mirror_id`) REFERENCES `mirrors`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `requests` (
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
            CREATE TABLE IF NOT EXISTS `visits` (
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


            $step = 'ساخت جدول‌های پایش و محتوا';
            require_once __DIR__ . '/includes/monitoring.php';
            ensureMonitorSchema($pdo);
            require_once __DIR__ . '/includes/cms.php';
            ensureCmsSchema($pdo);

            $step = 'ساخت حساب مدیر';
            $adminId = null;
            $existingAdmins = $pdo->query("SELECT id, username, password_hash FROM users WHERE role = 'admin' ORDER BY id ASC")->fetchAll();
            if ($existingAdmins) {
                foreach ($existingAdmins as $existingAdmin) {
                    if ((string)$existingAdmin['username'] === $adminUsername && password_verify($adminPassword, (string)$existingAdmin['password_hash'])) {
                        $adminId = (int)$existingAdmin['id'];
                        break;
                    }
                }
                if ($adminId === null) {
                    throw new RuntimeException('حساب مدیر در تلاش نصب قبلی ایجاد شده است. برای ادامه، همان نام کاربری و رمز مدیر قبلی را وارد کنید.');
                }
            } else {
                $existingUser = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
                $existingUser->execute([$adminUsername]);
                if ($existingUser->fetchColumn() !== false) {
                    throw new RuntimeException('این نام کاربری قبلاً وجود دارد. نام دیگری برای مدیر انتخاب کنید.');
                }
                $adminPassHash = password_hash($adminPassword, PASSWORD_DEFAULT);
                if (!is_string($adminPassHash)) {
                    throw new RuntimeException('ساخت هش امن رمز عبور ناموفق بود.');
                }
                $createAdmin = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'admin')");
                $createAdmin->execute([$adminUsername, $adminPassHash]);
                $adminId = (int)$pdo->lastInsertId();
            }

            $step = 'تنظیمات اولیه';
            $seedCategory = $pdo->prepare("INSERT IGNORE INTO categories (name, slug, description, type, sort_order) VALUES (?, ?, ?, ?, ?)");
            $seedCategory->execute(['دسته‌بندی عمومی', 'general', 'دسته‌بندی پیش‌فرض برای میرورها', 'software', 1]);

            $step = 'نهایی‌سازی نصب';
            $pdo->exec("UPDATE mirrorhub_installation SET status = 'complete', schema_version = 1 WHERE id = 1");
            $markerPath = __DIR__ . '/.mirrorhub-installed';
            if (@file_put_contents($markerPath, "MirrorHub installation completed.\n", LOCK_EX) !== false) {
                @chmod($markerPath, 0640);
            } else {
                error_log('MirrorHub Setup: installation completed but lock marker could not be written; database state remains authoritative.');
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = $adminId;
            $_SESSION['username'] = $adminUsername;
            $_SESSION['role'] = 'admin';
            unset($_SESSION['mirrorhub_setup_csrf']);

            if (!@unlink(__FILE__)) {
                error_log('MirrorHub Setup: automatic setup.php removal failed; completed-installation lock remains active.');
            }
            header('Cache-Control: no-store');
            header('Location: ' . rtrim($siteUrl, '/') . '/admin/dashboard.php', true, 303);
            exit;
        } catch (Throwable $error) {
            error_log('MirrorHub Setup failed during ' . $step . ': ' . $error->getMessage());
            $errors[] = $error instanceof RuntimeException
                ? $error->getMessage()
                : 'عملیات در مرحله «' . $step . '» کامل نشد. خطا ثبت شد و داده‌های دیتابیس حذف نشدند؛ می‌توانید تنظیمات را بررسی و دوباره تلاش کنید.';
        }
    }
}

$displayHost = htmlspecialchars($dbFields['host'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$displayPort = htmlspecialchars($dbFields['port'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$displayName = htmlspecialchars($dbFields['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$displayUser = htmlspecialchars($dbFields['user'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$displaySiteUrl = htmlspecialchars($siteUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$displayAdmin = htmlspecialchars($adminUsername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$displayCsrf = htmlspecialchars($setupCsrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="referrer" content="no-referrer">
<title>راه‌اندازی MirrorHub</title>
<style>
:root{color-scheme:dark;--bg:#0b100e;--panel:#121b17;--panel2:#17231c;--line:#293a2f;--text:#edf5ee;--muted:#a0b4a5;--accent:#b8ef72;--accent2:#74d7b0;--danger:#ff9b9b;--warn:#f6d38a}
*{box-sizing:border-box}html{min-width:320px}body{margin:0;min-height:100vh;background:radial-gradient(ellipse at 18% 6%,rgba(116,215,176,.14),transparent 34%),radial-gradient(ellipse at 84% 86%,rgba(184,239,114,.09),transparent 30%),var(--bg);color:var(--text);font-family:Tahoma,"Segoe UI",sans-serif;line-height:1.8}
.shell{width:min(1120px,calc(100% - 32px));margin:42px auto;display:grid;grid-template-columns:minmax(0,1.35fr) minmax(260px,.65fr);gap:24px;align-items:start}
.main,.aside{border:1px solid var(--line);border-radius:24px;background:rgba(18,27,23,.94);box-shadow:0 24px 70px rgba(0,0,0,.22);overflow:hidden}
.top{padding:30px 32px 24px;border-bottom:1px solid var(--line);background:linear-gradient(125deg,rgba(184,239,114,.07),transparent 58%)}
.brand{display:flex;align-items:center;gap:12px;margin-bottom:22px}.logo{display:grid;place-items:center;width:44px;height:44px;border-radius:14px;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#122017;font-weight:900;font-size:19px;box-shadow:0 8px 25px rgba(116,215,176,.15)}.brand-name{font-size:16px;font-weight:800;letter-spacing:.2px}.brand-sub{font-size:11px;color:var(--muted)}
h1{font-size:clamp(24px,3vw,34px);line-height:1.45;margin:0 0 8px;letter-spacing:-.5px}.intro{color:var(--muted);font-size:13px;margin:0;max-width:62ch}
.pills{display:flex;gap:8px;flex-wrap:wrap;margin-top:20px}.pill{font-size:11px;padding:4px 10px;border:1px solid var(--line);border-radius:999px;color:var(--muted);background:rgba(255,255,255,.02)}
.form-body{padding:26px 32px 32px}.error-list{margin:0 0 22px;padding:14px 18px;border:1px solid rgba(255,155,155,.35);border-radius:14px;background:rgba(255,155,155,.07);color:#ffd3d3;font-size:12px}.error-list ul{margin:6px 0 0;padding-right:20px}
.section{padding:22px 0;border-bottom:1px solid var(--line)}.section:first-child{padding-top:0}.section:last-of-type{border-bottom:0;padding-bottom:8px}.section-head{display:flex;gap:12px;align-items:flex-start;margin-bottom:18px}.step{display:grid;place-items:center;flex:0 0 34px;height:34px;border-radius:11px;background:rgba(184,239,114,.1);border:1px solid rgba(184,239,114,.2);color:var(--accent);font-weight:800;font-size:12px}.section h2{font-size:15px;margin:0 0 2px}.section-head p{font-size:11px;color:var(--muted);margin:0}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.field{min-width:0;display:flex;flex-direction:column;gap:7px}.field.full{grid-column:1/-1}.field label{font-size:12px;color:#d8e5da;font-weight:700}.field small{font-size:10px;color:var(--muted);line-height:1.6}
input{width:100%;height:46px;border:1px solid #34473a;border-radius:11px;background:#0c130f;color:var(--text);padding:10px 12px;font:inherit;font-size:13px;outline:none;transition:border-color .18s,box-shadow .18s}input:focus{border-color:var(--accent2);box-shadow:0 0 0 3px rgba(116,215,176,.1)}input::placeholder{color:#617666}input[dir=ltr]{text-align:left;direction:ltr}
.password-wrap{position:relative}.password-wrap input{padding-left:67px}.toggle-pass{position:absolute;left:7px;top:7px;height:32px;border:0;border-radius:8px;background:#1e3024;color:var(--muted);padding:0 8px;font-size:10px;cursor:pointer}
.note{margin-top:14px;padding:12px 14px;border-radius:12px;background:rgba(116,215,176,.06);border:1px solid rgba(116,215,176,.16);color:var(--muted);font-size:11px}
.status{display:none;margin-top:12px;padding:11px 13px;border:1px solid var(--line);border-radius:11px;font-size:12px}.status.visible{display:block}.status.success{color:#c6f6a2;border-color:rgba(184,239,114,.3);background:rgba(184,239,114,.06)}.status.error{color:#ffc2c2;border-color:rgba(255,155,155,.3);background:rgba(255,155,155,.06)}.status.warning{color:var(--warn);border-color:rgba(246,211,138,.28);background:rgba(246,211,138,.05)}
.actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:24px}.btn{border:1px solid transparent;min-height:46px;padding:10px 16px;border-radius:12px;font:inherit;font-size:12px;font-weight:800;cursor:pointer;transition:transform .15s,filter .15s}.btn:hover{filter:brightness(1.06);transform:translateY(-1px)}.btn-primary{background:linear-gradient(115deg,var(--accent),var(--accent2));color:#0a1710;flex:1}.btn-quiet{background:#1a2920;border-color:#314437;color:#d5e5d8}.btn:disabled{opacity:.55;cursor:wait;transform:none}
.footnote{font-size:10px;color:var(--muted);margin:16px 0 0;text-align:center}
.aside{padding:25px;position:sticky;top:24px}.aside h2{font-size:14px;margin:0 0 17px}.aside-intro{font-size:11px;color:var(--muted);margin:0 0 22px}
.rail{display:grid;gap:17px}.rail-item{display:grid;grid-template-columns:32px 1fr;gap:11px;align-items:start}.rail-icon{width:32px;height:32px;display:grid;place-items:center;background:var(--panel2);border:1px solid var(--line);border-radius:10px;color:var(--accent2);font-size:13px;font-weight:800}.rail-item strong{display:block;font-size:12px;margin:2px 0 3px}.rail-item p{margin:0;color:var(--muted);font-size:10px;line-height:1.8}.aside-sep{height:1px;background:var(--line);margin:24px 0}.security{font-size:11px;color:var(--muted);margin:0}.security strong{color:#dcebdc}.aside-foot{font-size:10px;color:#718775;margin:24px 0 0}
@media(max-width:850px){.shell{grid-template-columns:1fr;width:min(720px,calc(100% - 22px));margin:18px auto}.aside{position:static;grid-row:2}.top,.form-body{padding-left:22px;padding-right:22px}.aside{padding:22px}}
@media(max-width:520px){.grid{grid-template-columns:1fr}.field.full{grid-column:auto}.top,.form-body{padding:20px 17px}.shell{width:calc(100% - 16px);gap:14px}.main,.aside{border-radius:18px}.actions .btn{width:100%}.section-head{gap:10px}}
</style>
</head>
<body>
<div class="shell">
<main class="main">
  <header class="top">
    <div class="brand"><div class="logo">M</div><div><div class="brand-name">MirrorHub</div><div class="brand-sub">INSTALLATION WIZARD · راه‌اندازی امن</div></div></div>
    <h1>راه‌اندازی اولیه، در چند قدم</h1>
    <p class="intro">مشخصات دیتابیس و حساب مدیر را وارد کنید. MirrorHub پیش‌نیازها را بررسی می‌کند، ساختار لازم را ایجاد می‌کند و پس از تکمیل، شما را به پنل مدیریت وارد می‌کند.</p>
    <div class="pills"><span class="pill">PHP 8.2+</span><span class="pill">MySQL / MariaDB</span><span class="pill">بدون وابستگی خارجی</span></div>
  </header>
  <div class="form-body">
    <?php if ($errors): ?>
      <div class="error-list" role="alert"><strong>راه‌اندازی تکمیل نشد.</strong><ul><?php foreach ($errors as $setupError): ?><li><?= htmlspecialchars($setupError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form id="setup-form" method="post" action="<?= htmlspecialchars(mirrorhubSetupUrl(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" autocomplete="on">
      <input type="hidden" name="setup_csrf_token" value="<?= $displayCsrf ?>">
      <input type="hidden" name="setup_action" value="install">
      <section class="section">
        <div class="section-head"><div class="step">01</div><div><h2>اتصال پایگاه داده</h2><p>اطلاعات اتصال MySQL یا MariaDB روی هاست</p></div></div>
        <div class="grid">
          <div class="field"><label for="db_host">میزبان دیتابیس</label><input id="db_host" name="db_host" dir="ltr" value="<?= $displayHost ?>" placeholder="localhost" required autocomplete="off"></div>
          <div class="field"><label for="db_port">پورت</label><input id="db_port" name="db_port" dir="ltr" value="<?= $displayPort ?>" inputmode="numeric" required></div>
          <div class="field"><label for="db_name">نام دیتابیس</label><input id="db_name" name="db_name" dir="ltr" value="<?= $displayName ?>" placeholder="mirrorhub" required autocomplete="off"></div>
          <div class="field"><label for="db_user">نام کاربری دیتابیس</label><input id="db_user" name="db_user" dir="ltr" value="<?= $displayUser ?>" required autocomplete="username"></div>
          <div class="field full"><label for="db_pass">رمز عبور دیتابیس</label><div class="password-wrap"><input id="db_pass" name="db_pass" type="password" dir="ltr" required autocomplete="new-password"><button class="toggle-pass" type="button" data-target="db_pass">نمایش رمز</button></div><small>رمز فقط برای اتصال استفاده می‌شود و در صفحه‌ی نتیجه نمایش داده نخواهد شد.</small></div>
        </div>
        <div class="actions"><button class="btn btn-quiet" id="test-db" type="button">آزمایش اتصال دیتابیس</button></div>
        <div id="db-status" class="status" role="status" aria-live="polite"></div>
        <div class="note">در صورت نداشتن مجوز ساخت دیتابیس، آن را از پنل هاست ایجاد کنید. کاربر دیتابیس باید مجوز ایجاد جداول و ایندکس‌ها را داشته باشد.</div>
      </section>
      <section class="section">
        <div class="section-head"><div class="step">02</div><div><h2>مشخصات سایت</h2><p>آدرس پایه برای لینک‌ها و انتقال‌های پس از ورود</p></div></div>
        <div class="grid">
          <div class="field full"><label for="site_url">آدرس اصلی سایت</label><input id="site_url" name="site_url" type="url" dir="ltr" value="<?= $displaySiteUrl ?>" placeholder="https://example.com" required><small>اگر برنامه در زیرشاخه‌ای مانند /beta قرار دارد، آن مسیر را هم وارد کنید. در محیط عمومی از HTTPS استفاده کنید.</small></div>
        </div>
      </section>
      <section class="section">
        <div class="section-head"><div class="step">03</div><div><h2>حساب مدیر</h2><p>حسابی که پس از نصب با آن وارد داشبورد می‌شوید</p></div></div>
        <div class="grid">
          <div class="field full"><label for="admin_username">نام کاربری مدیر</label><input id="admin_username" name="admin_username" dir="ltr" value="<?= $displayAdmin ?>" minlength="3" maxlength="50" pattern="[A-Za-z0-9_.-]{3,50}" required autocomplete="username"><small>۳ تا ۵۰ نویسه؛ فقط حروف انگلیسی، عدد، نقطه، خط تیره و زیرخط.</small></div>
          <div class="field"><label for="admin_password">رمز عبور مدیر</label><div class="password-wrap"><input id="admin_password" name="admin_password" type="password" dir="ltr" minlength="12" required autocomplete="new-password"><button class="toggle-pass" type="button" data-target="admin_password">نمایش رمز</button></div></div>
          <div class="field"><label for="confirm_password">تکرار رمز عبور</label><div class="password-wrap"><input id="confirm_password" name="confirm_password" type="password" dir="ltr" minlength="12" required autocomplete="new-password"><button class="toggle-pass" type="button" data-target="confirm_password">نمایش رمز</button></div></div>
        </div>
        <div class="note">رمز عبور باید حداقل ۱۲ نویسه داشته باشد. رمز به‌صورت هش امن ذخیره می‌شود و نسخه‌ی خام آن در دیتابیس قرار نمی‌گیرد.</div>
      </section>
      <div class="actions"><button class="btn btn-primary" id="install-submit" type="submit">نصب MirrorHub و ورود به مدیریت <span aria-hidden="true">←</span></button></div>
      <p class="footnote">با اجرای نصب، دیتابیس انتخاب‌شده بررسی می‌شود. دیتابیس دارای داده یا نصب کامل، بدون بازنویسی متوقف خواهد شد.</p>
    </form>
  </div>
</main>
<aside class="aside">
  <h2>مراحل راه‌اندازی</h2>
  <p class="aside-intro">هر مرحله قبل از ادامه بررسی می‌شود. اگر عملیات به مشکل بخورد، پیام خطا و اقدام پیشنهادی نمایش داده خواهد شد.</p>
  <div class="rail">
    <div class="rail-item"><div class="rail-icon">✓</div><div><strong>بررسی محیط</strong><p>نسخه PHP، افزونه PDO MySQL و توانایی نوشتن فایل تنظیمات هنگام اجرا بررسی می‌شوند.</p></div></div>
    <div class="rail-item"><div class="rail-icon">DB</div><div><strong>اتصال دیتابیس</strong><p>اتصال به سرور و دیتابیس هدف آزمایش می‌شود. در صورت نیاز، ساخت دیتابیس هم امتحان خواهد شد.</p></div></div>
    <div class="rail-item"><div class="rail-icon">▤</div><div><strong>آماده‌سازی ساختار</strong><p>جدول‌های اصلی، پایش، محتوا و تنظیمات نصب اولیه ساخته می‌شوند.</p></div></div>
    <div class="rail-item"><div class="rail-icon">↗</div><div><strong>ورود مدیر</strong><p>پس از تکمیل، قفل نصب ثبت می‌شود و نشست مدیر به‌صورت امن ساخته خواهد شد.</p></div></div>
  </div>
  <div class="aside-sep"></div>
  <p class="security"><strong>حفاظت از اطلاعات:</strong> رمز دیتابیس در HTML یا لاگ خطا چاپ نمی‌شود. نصب کامل‌شده دوباره اجرا نخواهد شد و دیتابیس دارای داده‌ی ناشناس دست‌کاری نمی‌شود.</p>
  <p class="aside-foot">MirrorHub · راه‌اندازی مستقل، بدون CDN و وابستگی به دیتابیس از پیش پیکربندی‌شده</p>
</aside>
</div>
<script>
(() => {
  const form = document.getElementById('setup-form');
  const testButton = document.getElementById('test-db');
  const submitButton = document.getElementById('install-submit');
  const statusBox = document.getElementById('db-status');
  const showStatus = (message, kind) => {
    statusBox.textContent = message;
    statusBox.className = 'status visible ' + kind;
  };
  document.querySelectorAll('[data-target]').forEach((button) => {
    button.addEventListener('click', () => {
      const field = document.getElementById(button.getAttribute('data-target'));
      if (!field) return;
      const show = field.type === 'password';
      field.type = show ? 'text' : 'password';
      button.textContent = show ? 'مخفی‌کردن' : 'نمایش رمز';
    });
  });
  testButton.addEventListener('click', async () => {
    const requiredNames = ['db_host', 'db_port', 'db_name', 'db_user', 'db_pass'];
    for (const name of requiredNames) {
      const input = form.elements.namedItem(name);
      if (input && !input.reportValidity()) return;
    }
    const data = new FormData(form);
    data.set('setup_action', 'test_connection');
    testButton.disabled = true;
    showStatus('در حال بررسی اتصال…', 'warning');
    try {
      const response = await fetch(window.location.pathname, {
        method: 'POST',
        body: data,
        headers: {'Accept': 'application/json'},
        credentials: 'same-origin',
        cache: 'no-store'
      });
      const result = await response.json();
      showStatus(result.message || 'پاسخ نامشخص از سرور دریافت شد.', result.kind || (result.ok ? 'success' : 'error'));
    } catch (error) {
      showStatus('پاسخ قابل خواندن از سرور دریافت نشد. اتصال وب‌سرور را بررسی کنید.', 'error');
    } finally {
      testButton.disabled = false;
    }
  });
  form.addEventListener('submit', (event) => {
    const password = document.getElementById('admin_password');
    const confirm = document.getElementById('confirm_password');
    if (password.value !== confirm.value) {
      event.preventDefault();
      confirm.setCustomValidity('تکرار رمز عبور با رمز اصلی یکسان نیست.');
      confirm.reportValidity();
      confirm.setCustomValidity('');
      return;
    }
    submitButton.disabled = true;
    submitButton.textContent = 'در حال نصب؛ لطفاً صفحه را نبندید…';
    statusBox.className = 'status';
  });
})();
</script>
</body>
</html>
