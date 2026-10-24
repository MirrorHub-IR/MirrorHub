<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/layout.php';
requireAdmin();

const MIRRORHUB_BACKUP_VERSION = 2;
$backupTables = [
    'users', 'categories', 'mirrors', 'mirror_categories', 'requests', 'request_links',
    'visits', 'mirror_protocols', 'request_protocols', 'cms_articles',
    'mirror_checks', 'mirror_status', 'mirror_daily_stats', 'mirror_incidents', 'monitor_settings',
];

function backupTableExists(PDO $pdo, string $table): bool {
    if (!preg_match('/^[a-z_]+$/', $table)) return false;
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function tableColumns(PDO $pdo, string $table): array {
    if (!preg_match('/^[a-z_]+$/', $table)) {
        throw new RuntimeException('نام جدول نامعتبر است.');
    }
    $rows = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    return array_column($rows, 'Field');
}

function safeColumns(array $row, array $allowedColumns, bool $includeId = true): array {
    $data = [];
    foreach ($row as $column => $value) {
        if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$column)) continue;
        if (!in_array($column, $allowedColumns, true)) continue;
        if (!$includeId && $column === 'id') continue;
        $data[$column] = $value;
    }
    return $data;
}

function insertRow(PDO $pdo, string $table, array $row, array $allowedColumns, bool $includeId = true): int {
    $data = safeColumns($row, $allowedColumns, $includeId);
    if (!$data) return 0;
    $columns = array_keys($data);
    foreach ($columns as $column) {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $column)) throw new RuntimeException('نام ستون نامعتبر است.');
    }
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $stmt = $pdo->prepare("INSERT INTO $table (" . implode(', ', $columns) . ") VALUES ($placeholders)");
    $stmt->execute(array_values($data));
    return (int)$pdo->lastInsertId();
}

function findByValue(PDO $pdo, string $table, string $column, $value): ?int {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $column)) return null;
    $stmt = $pdo->prepare("SELECT id FROM $table WHERE $column = ? LIMIT 1");
    $stmt->execute([$value]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int)$id;
}

function fetchBackupRows(array $backup, string $table): array {
    if (!isset($backup['tables'][$table]['rows']) || !is_array($backup['tables'][$table]['rows'])) {
        throw new RuntimeException("ساختار جدول «{$table}» در فایل پشتیبان نامعتبر است.");
    }
    return $backup['tables'][$table]['rows'];
}

function mappedId(array $map, $oldId, string $label): ?int {
    if ($oldId === null || $oldId === '' || (int)$oldId === 0) return null;
    $oldId = (int)$oldId;
    if (!isset($map[$oldId])) throw new RuntimeException("ارتباط «{$label}» در فایل پشتیبان قابل بازیابی نیست.");
    return (int)$map[$oldId];
}

if (($_GET['action'] ?? '') === 'export') {
    $payload = [
        'format' => 'mirrorhub-backup',
        'version' => MIRRORHUB_BACKUP_VERSION,
        'exported_at' => date('c'),
        'site' => SITE_NAME,
        'tables' => [],
    ];

    // Use a consistent snapshot so related tables are not exported from different points in time.
    $pdo->beginTransaction();
    try {
        foreach ($backupTables as $table) {
            if (!backupTableExists($pdo, $table)) {
                $payload['tables'][$table] = ['present' => false, 'columns' => [], 'rows' => []];
                continue;
            }
            $payload['tables'][$table] = [
                'present' => true,
                'columns' => tableColumns($pdo, $table),
                'rows' => $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC),
            ];
        }
        $pdo->commit();
    } catch (Throwable $exportError) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exportError;
    }

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $filename = 'mirrorhub-backup-' . date('Ymd-His') . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($json));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $json;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['backup_action'] ?? '') === 'import') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        flash('error', 'توکن امنیتی نامعتبر است.');
        redirect(SITE_URL . '/admin/backup.php');
    }

    $mode = ($_POST['restore_mode'] ?? 'merge') === 'replace' ? 'replace' : 'merge';
    if ($mode === 'replace' && empty($_POST['replace_confirm'])) {
        flash('error', 'برای جایگزینی کامل، تأیید حذف داده‌های فعلی الزامی است.');
        redirect(SITE_URL . '/admin/backup.php');
    }

    if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'فایل JSON معتبر انتخاب کنید.');
        redirect(SITE_URL . '/admin/backup.php');
    }

    $file = $_FILES['backup_file'];
    if (($file['size'] ?? 0) > 25 * 1024 * 1024) {
        flash('error', 'حجم فایل پشتیبان نباید بیشتر از ۲۵ مگابایت باشد.');
        redirect(SITE_URL . '/admin/backup.php');
    }
    if (strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION)) !== 'json') {
        flash('error', 'فقط فایل با پسوند JSON پذیرفته می‌شود.');
        redirect(SITE_URL . '/admin/backup.php');
    }

    $raw = file_get_contents($file['tmp_name']);
    if ($raw === false || trim($raw) === '') {
        flash('error', 'فایل پشتیبان خالی یا غیرقابل خواندن است.');
        redirect(SITE_URL . '/admin/backup.php');
    }

    try {
        $backup = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $backupVersion = (int)($backup['version'] ?? 0);
        if (($backup['format'] ?? '') !== 'mirrorhub-backup' || !in_array($backupVersion, [1, MIRRORHUB_BACKUP_VERSION], true)) {
            throw new RuntimeException('فرمت یا نسخه فایل پشتیبان با MirrorHub سازگار نیست.');
        }
        if (!isset($backup['tables']) || !is_array($backup['tables'])) throw new RuntimeException('بخش tables در فایل پشتیبان وجود ندارد.');

        $legacyMissingTables = [];
        $extendedTables = ['request_links', 'mirror_checks', 'mirror_status', 'mirror_daily_stats', 'mirror_incidents', 'monitor_settings'];

        // Restore may also be used to bring request_links into an older installation.
        if (!backupTableExists($pdo, 'request_links')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `request_links` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `request_id` INT NOT NULL,
                `url` VARCHAR(1000) NOT NULL,
                `sort_order` INT DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_request_links_request` (`request_id`, `sort_order`),
                FOREIGN KEY (`request_id`) REFERENCES `requests`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        $availableTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $columnMap = [];
        foreach ($backupTables as $table) {
            if (!isset($backup['tables'][$table]) || !is_array($backup['tables'][$table]) || !is_array($backup['tables'][$table]['rows'] ?? null)) {
                if ($backupVersion === 1 && in_array($table, $extendedTables, true)) {
                    $legacyMissingTables[] = $table;
                    $backup['tables'][$table] = ['present' => false, 'columns' => [], 'rows' => []];
                } elseif ($table === 'cms_articles') {
                    $backup['tables'][$table] = ['present' => false, 'columns' => [], 'rows' => []];
                } else {
                    throw new RuntimeException("فایل پشتیبان ناقص یا نامعتبر است: «{$table}».");
                }
            }
            if (($backup['tables'][$table]['present'] ?? true) === false) {
                $backup['tables'][$table]['rows'] = [];
            }
            if (!in_array($table, $availableTables, true)) {
                throw new RuntimeException("جدول «{$table}» در پایگاه داده فعلی موجود نیست.");
            }
            $columnMap[$table] = tableColumns($pdo, $table);
        }

        // Old backups omit monitoring history and multi-link requests. Never erase live data
        // through "replace" when the selected archive cannot restore those newer tables.
        if ($mode === 'replace' && $legacyMissingTables) {
            throw new RuntimeException('این فایل پشتیبان قدیمی است و تمام جدول‌های نسخهٔ فعلی را ندارد؛ برای جلوگیری از حذف تاریخچه، بازیابی جایگزینی لغو شد. برای بازیابی قدیمی از حالت «حفظ اطلاعات فعلی» استفاده کنید.');
        }

        $pdo->beginTransaction();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        if ($mode === 'replace') {
            foreach (['mirror_checks','mirror_status','mirror_daily_stats','mirror_incidents','monitor_settings','cms_articles','request_links','mirror_protocols','request_protocols','mirror_categories','visits','requests','mirrors','categories','users'] as $table) {
                $pdo->exec("DELETE FROM `$table`");
            }
            foreach (['users','categories','mirrors','requests','visits','mirror_categories','request_links','mirror_protocols','request_protocols','cms_articles','mirror_checks','mirror_status','mirror_daily_stats','mirror_incidents','monitor_settings'] as $table) {
                foreach (fetchBackupRows($backup, $table) as $row) insertRow($pdo, $table, $row, $columnMap[$table], true);
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $pdo->commit();
            flash('success', 'داده‌های فعلی حذف و پشتیبان با موفقیت جایگزین شد.');
        } else {
            $maps = ['users'=>[],'categories'=>[],'mirrors'=>[],'requests'=>[]];

            foreach (fetchBackupRows($backup, 'users') as $row) {
                $oldId = (int)($row['id'] ?? 0);
                $existing = findByValue($pdo, 'users', 'username', $row['username'] ?? '');
                $newId = $existing ?? insertRow($pdo, 'users', $row, $columnMap['users'], false);
                if (!$oldId || !$newId) throw new RuntimeException('رکورد کاربر نامعتبر است.');
                $maps['users'][$oldId] = $newId;
            }

            $pendingCategories = fetchBackupRows($backup, 'categories');
            $guard = count($pendingCategories) + 1;
            while ($pendingCategories && $guard-- > 0) {
                $progress = false;
                foreach ($pendingCategories as $key => $row) {
                    $oldId = (int)($row['id'] ?? 0);
                    $existing = findByValue($pdo, 'categories', 'slug', $row['slug'] ?? '');
                    if ($existing) {
                        $maps['categories'][$oldId] = $existing;
                        unset($pendingCategories[$key]);
                        $progress = true;
                        continue;
                    }
                    $parentId = $row['parent_id'] ?? null;
                    if ($parentId !== null && $parentId !== '' && !isset($maps['categories'][(int)$parentId])) continue;
                    $row['parent_id'] = mappedId($maps['categories'], $parentId, 'والد دسته‌بندی');
                    $maps['categories'][$oldId] = insertRow($pdo, 'categories', $row, $columnMap['categories'], false);
                    unset($pendingCategories[$key]);
                    $progress = true;
                }
                if (!$progress) throw new RuntimeException('ساختار والد/فرزند دسته‌بندی‌ها نامعتبر است.');
            }

            foreach (fetchBackupRows($backup, 'mirrors') as $row) {
                $oldId = (int)($row['id'] ?? 0);
                $existing = findByValue($pdo, 'mirrors', 'slug', $row['slug'] ?? '');
                $newId = $existing ?? insertRow($pdo, 'mirrors', $row, $columnMap['mirrors'], false);
                if (!$oldId || !$newId) throw new RuntimeException('رکورد میرور نامعتبر است.');
                $maps['mirrors'][$oldId] = $newId;
            }

            foreach (fetchBackupRows($backup, 'requests') as $row) {
                $oldId = (int)($row['id'] ?? 0);
                if (!$oldId) throw new RuntimeException('شناسه درخواست نامعتبر است.');
                $row['parent_category_id'] = mappedId($maps['categories'], $row['parent_category_id'] ?? null, 'والد درخواست');
                $row['category_id'] = mappedId($maps['categories'], $row['category_id'] ?? null, 'دسته درخواست');
                $row['mirror_id'] = mappedId($maps['mirrors'], $row['mirror_id'] ?? null, 'میرور درخواست');
                $row['reviewed_by'] = mappedId($maps['users'], $row['reviewed_by'] ?? null, 'بررسی‌کننده درخواست');
                $maps['requests'][$oldId] = insertRow($pdo, 'requests', $row, $columnMap['requests'], false);
            }

            foreach (fetchBackupRows($backup, 'visits') as $row) insertRow($pdo, 'visits', $row, $columnMap['visits'], false);

            foreach (fetchBackupRows($backup, 'mirror_categories') as $row) {
                $mirrorId = mappedId($maps['mirrors'], $row['mirror_id'] ?? null, 'میرور دسته‌بندی');
                $categoryId = mappedId($maps['categories'], $row['category_id'] ?? null, 'دسته‌بندی میرور');
                if ($mirrorId && $categoryId) {
                    $stmt = $pdo->prepare('INSERT IGNORE INTO mirror_categories (mirror_id, category_id) VALUES (?, ?)');
                    $stmt->execute([$mirrorId, $categoryId]);
                }
            }

            foreach (fetchBackupRows($backup, 'mirror_protocols') as $row) {
                $mirrorId = mappedId($maps['mirrors'], $row['mirror_id'] ?? null, 'پروتکل میرور');
                if ($mirrorId) {
                    $stmt = $pdo->prepare('INSERT IGNORE INTO mirror_protocols (mirror_id, protocol, custom_label, sort_order) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$mirrorId, $row['protocol'] ?? '', $row['custom_label'] ?? null, (int)($row['sort_order'] ?? 0)]);
                }
            }

            foreach (fetchBackupRows($backup, 'request_protocols') as $row) {
                $requestId = mappedId($maps['requests'], $row['request_id'] ?? null, 'پروتکل درخواست');
                if ($requestId) {
                    $stmt = $pdo->prepare('INSERT IGNORE INTO request_protocols (request_id, protocol, custom_label, sort_order) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$requestId, $row['protocol'] ?? '', $row['custom_label'] ?? null, (int)($row['sort_order'] ?? 0)]);
                }
            }

            foreach (fetchBackupRows($backup, 'request_links') as $row) {
                $requestId = mappedId($maps['requests'], $row['request_id'] ?? null, 'لینک درخواست');
                if (!$requestId) continue;
                $url = (string)($row['url'] ?? '');
                $exists = $pdo->prepare('SELECT id FROM request_links WHERE request_id = ? AND url = ? LIMIT 1');
                $exists->execute([$requestId, $url]);
                if (!$exists->fetchColumn()) {
                    $row['request_id'] = $requestId;
                    insertRow($pdo, 'request_links', $row, $columnMap['request_links'], false);
                }
            }

            foreach (fetchBackupRows($backup, 'mirror_checks') as $row) {
                $mirrorId = mappedId($maps['mirrors'], $row['mirror_id'] ?? null, 'تاریخچهٔ بررسی میرور');
                if (!$mirrorId) continue;
                $exists = $pdo->prepare("SELECT id FROM mirror_checks
                    WHERE mirror_id = ? AND checked_at = ? AND status = ?
                    AND response_ms <=> ? AND http_code <=> ? LIMIT 1");
                $exists->execute([$mirrorId, $row['checked_at'] ?? null, $row['status'] ?? '', $row['response_ms'] ?? null, $row['http_code'] ?? null]);
                if (!$exists->fetchColumn()) {
                    $row['mirror_id'] = $mirrorId;
                    insertRow($pdo, 'mirror_checks', $row, $columnMap['mirror_checks'], false);
                }
            }

            foreach (fetchBackupRows($backup, 'mirror_status') as $row) {
                $mirrorId = mappedId($maps['mirrors'], $row['mirror_id'] ?? null, 'وضعیت میرور');
                if (!$mirrorId) continue;
                $exists = $pdo->prepare('SELECT mirror_id FROM mirror_status WHERE mirror_id = ? LIMIT 1');
                $exists->execute([$mirrorId]);
                if (!$exists->fetchColumn()) {
                    $row['mirror_id'] = $mirrorId;
                    insertRow($pdo, 'mirror_status', $row, $columnMap['mirror_status'], false);
                }
            }

            foreach (fetchBackupRows($backup, 'mirror_daily_stats') as $row) {
                $mirrorId = mappedId($maps['mirrors'], $row['mirror_id'] ?? null, 'آمار روزانهٔ میرور');
                if (!$mirrorId) continue;
                $exists = $pdo->prepare('SELECT mirror_id FROM mirror_daily_stats WHERE mirror_id = ? AND day = ? LIMIT 1');
                $exists->execute([$mirrorId, $row['day'] ?? null]);
                if (!$exists->fetchColumn()) {
                    $row['mirror_id'] = $mirrorId;
                    insertRow($pdo, 'mirror_daily_stats', $row, $columnMap['mirror_daily_stats'], false);
                }
            }

            foreach (fetchBackupRows($backup, 'mirror_incidents') as $row) {
                $mirrorId = mappedId($maps['mirrors'], $row['mirror_id'] ?? null, 'رخداد میرور');
                if (!$mirrorId) continue;
                $exists = $pdo->prepare("SELECT id FROM mirror_incidents
                    WHERE mirror_id = ? AND started_at = ? AND status = ? LIMIT 1");
                $exists->execute([$mirrorId, $row['started_at'] ?? null, $row['status'] ?? '']);
                if (!$exists->fetchColumn()) {
                    $row['mirror_id'] = $mirrorId;
                    insertRow($pdo, 'mirror_incidents', $row, $columnMap['mirror_incidents'], false);
                }
            }

            foreach (fetchBackupRows($backup, 'monitor_settings') as $row) {
                $settingKey = (string)($row['skey'] ?? '');
                if ($settingKey === '') continue;
                $exists = $pdo->prepare('SELECT skey FROM monitor_settings WHERE skey = ? LIMIT 1');
                $exists->execute([$settingKey]);
                if (!$exists->fetchColumn()) {
                    insertRow($pdo, 'monitor_settings', $row, $columnMap['monitor_settings'], false);
                }
            }

            // CMS articles share the same category and user records as the rest of MirrorHub.
            foreach (fetchBackupRows($backup, 'cms_articles') as $row) {
                $slug = (string)($row['slug'] ?? '');
                if ($slug === '' || findByValue($pdo, 'cms_articles', 'slug', $slug)) continue;
                $row['category_id'] = mappedId($maps['categories'], $row['category_id'] ?? null, 'دسته مقاله');
                $row['created_by'] = mappedId($maps['users'], $row['created_by'] ?? null, 'نویسنده مقاله');
                $row['updated_by'] = mappedId($maps['users'], $row['updated_by'] ?? null, 'ویرایشگر مقاله');
                insertRow($pdo, 'cms_articles', $row, $columnMap['cms_articles'], false);
            }

            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $pdo->commit();
            flash('success', 'داده‌های پشتیبان با حفظ اطلاعات فعلی به سیستم اضافه شد.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $pdo->rollBack();
        }
        flash('error', 'بازیابی انجام نشد: ' . $e->getMessage());
    }

    redirect(SITE_URL . '/admin/backup.php');
}

renderHeader('پشتیبان‌گیری و بازیابی', true);
?>

<section class="section backup-page">
    <div class="page-hero">
        <div class="page-hero-icon"><?= icon('database', 24) ?></div>
        <div>
            <span class="eyebrow">MirrorHub • Data Safety</span>
            <h1 class="section-title">پشتیبان‌گیری و بازیابی</h1>
            <p class="section-desc">از تمام داده‌های MirrorHub یک فایل JSON نسخه‌دار بسازید یا همان ساختار را دوباره وارد کنید.</p>
        </div>
    </div>

    <div class="alert alert-warning" role="note">
        فایل پشتیبان شامل تاریخچهٔ پایش و تنظیمات خصوصی اعلان‌ها، از جمله مقادیر محرمانهٔ واردشده در پنل است. فایل را در مخزن عمومی یا فضای اشتراکی منتشر نکنید و پس از انتقال بین سرورها، اعتبارنامه‌های اعلان را بازبینی کنید.
    </div>

    <div class="backup-actions-grid">
        <div class="backup-card">
            <div class="backup-card-head">
                <span class="backup-icon"><?= icon('database', 21) ?></span>
                <div><h2>خروجی داده‌ها</h2><p>تمام جدول‌های MirrorHub با ستون‌ها و رکوردها داخل یک JSON استاندارد ذخیره می‌شوند.</p></div>
            </div>
            <a href="<?= SITE_URL ?>/admin/backup.php?action=export" class="btn btn-primary backup-download">دانلود فایل پشتیبان</a>
            <div class="backup-meta">
                <span class="badge badge-default">JSON</span><span class="badge badge-default">نسخه <?= MIRRORHUB_BACKUP_VERSION ?></span><span class="badge badge-default">شامل روابط و پروتکل‌ها</span>
            </div>
        </div>

        <div class="backup-card">
            <div class="backup-card-head">
                <span class="backup-icon"><?= icon('inbox', 21) ?></span>
                <div><h2>ورودی داده‌ها</h2><p>فایل JSON را بکشید و رها کنید یا روی ناحیه زیر کلیک کنید.</p></div>
            </div>

            <form action="<?= SITE_URL ?>/admin/backup.php" method="POST" enctype="multipart/form-data" id="backup-import-form">
                <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="backup_action" value="import">

                <label class="import-zone" id="backup-dropzone" for="backup-file">
                    <input type="file" id="backup-file" name="backup_file" accept=".json,application/json" required>
                    <div>
                        <div class="import-zone-icon"><?= icon('inbox', 25) ?></div>
                        <strong>فایل JSON را اینجا رها کنید</strong>
                        <span>یا برای انتخاب فایل کلیک کنید • حداکثر ۲۵ مگابایت</span>
                    </div>
                </label>

                <div class="file-pill" id="backup-file-pill">
                    <div class="file-pill-main"><span><?= icon('check', 15) ?></span><strong id="backup-file-name">فایل انتخاب شد</strong><span id="backup-file-size"></span></div>
                    <span class="badge badge-success">JSON</span>
                </div>

                <div class="import-options">
                    <div class="form-section-head" style="margin-bottom:.7rem;">
                        <span class="step-badge">۱</span><div><h2>نحوه بازیابی</h2><p>مشخص کنید اطلاعات فعلی چه اتفاقی بیفتد.</p></div>
                    </div>
                    <div class="restore-modes">
                        <label class="restore-mode"><input type="radio" name="restore_mode" value="merge" checked><span class="restore-mode-mark"></span><span class="restore-mode-copy"><strong>حفظ اطلاعات فعلی</strong><small>داده‌های موجود باقی می‌مانند و داده‌های پشتیبان اضافه می‌شوند.</small></span></label>
                        <label class="restore-mode"><input type="radio" name="restore_mode" value="replace"><span class="restore-mode-mark"></span><span class="restore-mode-copy"><strong>جایگزینی کامل</strong><small>داده‌های فعلی حذف می‌شوند و فایل پشتیبان جایگزین آن‌ها می‌شود.</small></span></label>
                    </div>
                    <label class="replace-confirm" id="replace-confirm"><input type="checkbox" name="replace_confirm" value="1"><span>تأیید می‌کنم که در حالت «جایگزینی کامل»، داده‌های فعلی MirrorHub حذف و با محتوای فایل پشتیبان جایگزین خواهند شد.</span></label>
                    <button type="submit" class="btn btn-primary backup-submit" id="backup-submit" disabled>بازیابی داده‌ها</button>
                </div>
            </form>
        </div>
    </div>
</section>

<?php renderFooter(); ?>
