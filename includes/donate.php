<?php
declare(strict_types=1);

/** Donate settings; schema is initialized lazily for existing installations. */
function donateDefaults(): array {
    return [
        'page_title' => 'با هم، اینترنت آزادتر و دسترس‌پذیرتر می‌سازیم',
        'page_subtitle' => 'MirrorHub یک پروژهٔ مستقل برای پیدا کردن و پایش میرورهای نرم‌افزاری است.',
        'page_intro' => 'ساختن و نگهداری یک فهرست قابل‌اعتماد از میرورها به زمان، زیرساخت و همراهی جامعه نیاز دارد. اگر این پروژه برایتان مفید است، آن را به دیگران معرفی کنید، در توسعه‌اش مشارکت کنید یا از راه‌های ارتباطی با ما در تماس باشید.',
        'telegram_url' => '',
        'github_url' => '',
        'channel_1_name' => '', 'channel_1_url' => '', 'channel_1_description' => '',
        'channel_2_name' => '', 'channel_2_url' => '', 'channel_2_description' => '',
        'channel_3_name' => '', 'channel_3_url' => '', 'channel_3_description' => '',
        'channel_4_name' => '', 'channel_4_url' => '', 'channel_4_description' => '',
    ];
}

function ensureDonateSchema(PDO $pdo): bool {
    static $done = false;
    if ($done) return true;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `donate_settings` (
            `setting_key` VARCHAR(80) NOT NULL PRIMARY KEY,
            `setting_value` TEXT NOT NULL,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $done = true;
        return true;
    } catch (Throwable $e) {
        error_log('MirrorHub donate settings schema could not be initialized.');
        return false;
    }
}

function donateSettings(PDO $pdo): array {
    if (isset($GLOBALS['__mh_donate_settings']) && is_array($GLOBALS['__mh_donate_settings'])) {
        return $GLOBALS['__mh_donate_settings'];
    }
    $settings = donateDefaults();
    if (ensureDonateSchema($pdo)) {
        try {
            foreach ($pdo->query('SELECT setting_key, setting_value FROM donate_settings')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $key = (string)($row['setting_key'] ?? '');
                if (array_key_exists($key, $settings)) $settings[$key] = (string)$row['setting_value'];
            }
        } catch (Throwable $e) {
            error_log('MirrorHub donate settings could not be read.');
        }
    }
    return $GLOBALS['__mh_donate_settings'] = $settings;
}

function donateSaveSettings(PDO $pdo, array $values): void {
    if (!ensureDonateSchema($pdo)) throw new RuntimeException('ذخیره‌سازی تنظیمات صفحهٔ حمایت در دسترس نیست.');
    $allowed = donateDefaults();
    $stmt = $pdo->prepare('INSERT INTO donate_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $pdo->beginTransaction();
    try {
        foreach ($values as $key => $value) {
            if (array_key_exists($key, $allowed)) $stmt->execute([(string)$key, (string)$value]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    unset($GLOBALS['__mh_donate_settings']);
}

/** Only HTTPS links and validated mailto links are allowed in public contact cards. */
function donateValidateUrl(string $value): bool {
    $value = trim($value);
    if ($value === '') return true;
    if (strlen($value) > 500 || preg_match('/[\x00-\x20\x7f]/', $value)) return false;
    if (stripos($value, 'mailto:') === 0) {
        return filter_var(substr($value, 7), FILTER_VALIDATE_EMAIL) !== false;
    }
    $parts = parse_url($value);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') return false;
    if (empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return false;
    return filter_var($value, FILTER_VALIDATE_URL) !== false;
}
