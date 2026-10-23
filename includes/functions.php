<?php

require_once __DIR__ . '/jalali.php';

/* ============================================================
   Redirect
   ============================================================ */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/* ============================================================
   Sanitize
   ============================================================ */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/* ============================================================
   Asset URL
   نسخه‌بندی خودکار بر اساس زمان تغییر فایل تا مرورگر بعد از هر
   به‌روزرسانی، CSS/JS/لوگوی تازه را بگیرد و کش قدیمی نماند.
   ============================================================ */
function asset($path) {
    $path = ltrim((string)$path, '/');
    $file = dirname(__DIR__) . '/assets/' . $path;
    $version = @filemtime($file);

    return SITE_URL . '/assets/' . $path . ($version ? '?v=' . $version : '');
}

/* ============================================================
   CSRF
   ============================================================ */
function generateCsrfToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verifyCsrfToken($token) {
    if (empty($_SESSION[CSRF_TOKEN_NAME]) || empty($token)) return false;
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/* ============================================================
   Auth
   ============================================================ */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin';
}

function requireAdmin() {
    if (!isAdmin()) {
        flash('error', 'برای دسترسی به این بخش باید وارد شوید.');
        redirect(SITE_URL . '/admin/login.php');
    }
}

/* ============================================================
   Flash
   ============================================================ */
function flash($key, $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

/* ============================================================
   Protocol
   ============================================================ */
function getProtocolOptions() {
    return [
        'https' => 'HTTPS',
        'http'  => 'HTTP',
        'ftp'   => 'FTP',
        'rsync' => 'Rsync',
        'custom' => 'سفارشی',
    ];
}

function getProtocolLabel($protocol, $customLabel = null) {
    if ($protocol === 'custom') return $customLabel ?: 'سفارشی';
    return getProtocolOptions()[$protocol] ?? $customLabel ?? 'سفارشی';
}

function getProtocolBadgeClass($protocol) {
    return match ($protocol) {
        'https' => 'badge-success',
        'http'  => 'badge-warning',
        'ftp'   => 'badge-info',
        'rsync' => 'badge-primary',
        default => 'badge-default',
    };
}

function normalizeProtocols($protocols, $customProtocols = []) {
    $protocols = is_array($protocols) ? $protocols : [$protocols];
    if (is_array($customProtocols)) {
        $customList = $customProtocols;
    } else {
        $customList = [(string)$customProtocols];
    }
    $customValue = '';
    foreach ($customList as $value) {
        if (trim((string)$value) !== '') {
            $customValue = trim((string)$value);
            break;
        }
    }
    $allowed = array_keys(getProtocolOptions());
    $result = [];
    foreach ($protocols as $protocol) {
        $protocol = trim((string)$protocol);
        if (!in_array($protocol, $allowed, true)) continue;
        $custom = null;
        if ($protocol === 'custom') {
            $custom = mb_substr($customValue, 0, 100);
            if ($custom === '') continue;
        }
        $key = $protocol . '|' . ($custom ?? '');
        if (isset($result[$key])) continue;
        $result[$key] = ['protocol' => $protocol, 'custom_label' => $custom];
    }
    return array_values($result);
}

function hasTable($pdo, $table) {
    static $cache = [];
    $table = (string)$table;
    if (array_key_exists($table, $cache)) return $cache[$table];
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
        $cache[$table] = (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $cache[$table] = false;
    }
    return $cache[$table];
}

function getMirrorProtocols($pdo, $mirrorId) {
    if (hasTable($pdo, 'mirror_protocols')) {
        try {
            $stmt = $pdo->prepare("SELECT protocol, custom_label FROM mirror_protocols WHERE mirror_id = ? ORDER BY sort_order ASC, id ASC");
            $stmt->execute([(int)$mirrorId]);
            $rows = $stmt->fetchAll();
            if ($rows) return $rows;
        } catch (Throwable $e) {
            // Fall back to legacy protocol field.
        }
    }

    $mirror = getMirrorById($pdo, $mirrorId);
    if (!$mirror || empty($mirror['protocol'])) return [];
    return [[
        'protocol' => $mirror['protocol'] === 'other' ? 'custom' : $mirror['protocol'],
        'custom_label' => $mirror['protocol'] === 'other' ? 'سایر' : null
    ]];
}

function setMirrorProtocols($pdo, $mirrorId, array $protocols) {
    if (!hasTable($pdo, 'mirror_protocols')) return false;
    $pdo->prepare("DELETE FROM mirror_protocols WHERE mirror_id = ?")->execute([(int)$mirrorId]);
    $stmt = $pdo->prepare("INSERT INTO mirror_protocols (mirror_id, protocol, custom_label, sort_order) VALUES (?, ?, ?, ?)");
    foreach (array_values($protocols) as $i => $item) {
        $stmt->execute([(int)$mirrorId, $item['protocol'], $item['custom_label'] ?? null, $i]);
    }
    return true;
}

function getRequestProtocols($pdo, $requestId) {
    if (!hasTable($pdo, 'request_protocols')) return [];
    try {
        $stmt = $pdo->prepare("SELECT protocol, custom_label FROM request_protocols WHERE request_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([(int)$requestId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function setRequestProtocols($pdo, $requestId, array $protocols) {
    if (!hasTable($pdo, 'request_protocols')) return false;
    $pdo->prepare("DELETE FROM request_protocols WHERE request_id = ?")->execute([(int)$requestId]);
    $stmt = $pdo->prepare("INSERT INTO request_protocols (request_id, protocol, custom_label, sort_order) VALUES (?, ?, ?, ?)");
    foreach (array_values($protocols) as $i => $item) {
        $stmt->execute([(int)$requestId, $item['protocol'], $item['custom_label'] ?? null, $i]);
    }
    return true;
}

function getRequestLinks($pdo, $requestId) {
    if (!hasTable($pdo, 'request_links')) return [];
    try {
        $stmt = $pdo->prepare("SELECT url FROM request_links WHERE request_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([(int)$requestId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function setRequestLinks($pdo, $requestId, array $links) {
    if (!hasTable($pdo, 'request_links')) return false;
    $pdo->prepare("DELETE FROM request_links WHERE request_id = ?")->execute([(int)$requestId]);
    $stmt = $pdo->prepare("INSERT INTO request_links (request_id, url, sort_order) VALUES (?, ?, ?)");
    foreach (array_values($links) as $i => $url) {
        $stmt->execute([(int)$requestId, (string)$url, $i]);
    }
    return true;
}

function protocolBadges(array $protocols) {
    $html = '';
    foreach ($protocols as $item) {
        $protocol = $item['protocol'] ?? '';
        $label = getProtocolLabel($protocol, $item['custom_label'] ?? null);
        $html .= '<span class="badge ' . getProtocolBadgeClass($protocol) . ' protocol-badge">' . htmlspecialchars(strtoupper($label)) . '</span>';
    }
    return $html;
}

/* ============================================================
   Categories
   ============================================================ */
function getCategoryTypes() {
    return [
        'software' => 'نرم‌افزار',
        'provider' => 'ارائه‌دهنده',
    ];
}

function getCategoryTypeLabel($type) {
    $types = getCategoryTypes();
    return $types[$type] ?? 'نامشخص';
}

function getCategories($pdo, $parentId = null, $type = null) {
    if ($parentId === null) {
        if ($type !== null) {
            $stmt = $pdo->prepare("SELECT * FROM categories WHERE parent_id IS NULL AND type = ? ORDER BY sort_order ASC, name ASC");
            $stmt->execute([$type]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY sort_order ASC, name ASC");
            $stmt->execute();
        }
    } else {
        if ($type !== null) {
            $stmt = $pdo->prepare("SELECT * FROM categories WHERE parent_id = ? AND type = ? ORDER BY sort_order ASC, name ASC");
            $stmt->execute([(int)$parentId, $type]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM categories WHERE parent_id = ? ORDER BY sort_order ASC, name ASC");
            $stmt->execute([(int)$parentId]);
        }
    }
    return $stmt->fetchAll();
}

function getCategoriesByType($pdo, $type) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE type = ? ORDER BY sort_order ASC, name ASC");
    $stmt->execute([$type]);
    return $stmt->fetchAll();
}

function getAllCategories($pdo) {
    return $pdo->query("SELECT * FROM categories ORDER BY FIELD(type, 'software', 'provider'), sort_order ASC, name ASC")->fetchAll();
}

function getCategoryPath($pdo, $categoryId) {
    $path = [];
    $currentId = (int)$categoryId;
    $guard = 0;
    while ($currentId && $guard < 20) {
        $stmt = $pdo->prepare("SELECT id, name, slug, parent_id FROM categories WHERE id = ?");
        $stmt->execute([$currentId]);
        $cat = $stmt->fetch();
        if (!$cat) break;
        array_unshift($path, $cat);
        $currentId = (int)$cat['parent_id'];
        $guard++;
    }
    return $path;
}

function getCategoryBySlug($pdo, $slug) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

function getCategoryById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([(int)$id]);
    return $stmt->fetch() ?: null;
}

function uploadCategoryIcon($file, $oldIcon = null) {
    $uploadDir = __DIR__ . '/../uploads/category-icons/';
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Validate file
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'فایلی آپلود نشده است.'];
    }
    
    // Check file size (max 500KB)
    if ($file['size'] > 500 * 1024) {
        return ['success' => false, 'error' => 'حجم فایل نباید بیشتر از ۵۰۰ کیلوبایت باشد.'];
    }
    
    // Check file type
    $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'error' => 'فرمت فایل معتبر نیست. فقط PNG, JPG و SVG مجاز است.'];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('cat_icon_', true) . '.' . strtolower($extension);
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'error' => 'خطا در ذخیره فایل.'];
    }
    
    // Delete old icon if exists
    if ($oldIcon && file_exists($uploadDir . basename($oldIcon))) {
        @unlink($uploadDir . basename($oldIcon));
    }
    
    return ['success' => true, 'filename' => $filename];
}

function deleteCategoryIcon($icon) {
    if (empty($icon)) return false;
    
    $uploadDir = __DIR__ . '/../uploads/category-icons/';
    $filepath = $uploadDir . basename($icon);
    
    if (file_exists($filepath)) {
        return @unlink($filepath);
    }
    
    return false;
}

/* ============================================================
   Mirrors
   ============================================================ */
function getMirrorCategories($pdo, $mirrorId) {
    $stmt = $pdo->prepare("SELECT c.* FROM categories c
        INNER JOIN mirror_categories mc ON c.id = mc.category_id
        WHERE mc.mirror_id = ?
        ORDER BY c.sort_order ASC, c.name ASC");
    $stmt->execute([(int)$mirrorId]);
    return $stmt->fetchAll();
}

function getMirrorCategoryTags($pdo, $mirrorId) {
    $categories = getMirrorCategories($pdo, $mirrorId);
    $parents = [];
    foreach ($categories as $cat) {
        if (!empty($cat['parent_id'])) {
            $parent = getCategoryById($pdo, $cat['parent_id']);
            if ($parent) $parents[$parent['id']] = $parent;
        }
    }
    $all = array_merge($categories, array_values($parents));
    usort($all, function ($a, $b) {
        $aChild = !empty($a['parent_id']);
        $bChild = !empty($b['parent_id']);
        if ($aChild !== $bChild) return $aChild ? -1 : 1;
        return strcasecmp($a['name'], $b['name']);
    });
    return $all;
}

function setMirrorCategories($pdo, $mirrorId, array $categoryIds) {
    $pdo->prepare("DELETE FROM mirror_categories WHERE mirror_id = ?")->execute([(int)$mirrorId]);
    $categoryIds = array_filter(array_unique(array_map('intval', $categoryIds)), fn($id) => $id > 0);
    if (empty($categoryIds)) return;
    $stmt = $pdo->prepare("INSERT INTO mirror_categories (mirror_id, category_id) VALUES (?, ?)");
    foreach ($categoryIds as $cid) {
        $stmt->execute([(int)$mirrorId, $cid]);
    }
}

function getMirrorsByCategory($pdo, $categoryId, $onlyActive = true) {
    $sql = "SELECT DISTINCT m.* FROM mirrors m
        INNER JOIN mirror_categories mc ON m.id = mc.mirror_id
        WHERE mc.category_id = ?";
    if ($onlyActive) $sql .= " AND m.status = 'active'";
    $sql .= " ORDER BY m.name_fa ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([(int)$categoryId]);
    return $stmt->fetchAll();
}

function getRecentMirrors($pdo, $limit = 12) {
    $limit = max(1, (int)$limit);
    $stmt = $pdo->prepare("SELECT * FROM mirrors WHERE status = 'active' ORDER BY created_at DESC LIMIT $limit");
    $stmt->execute();
    $mirrors = $stmt->fetchAll();
    foreach ($mirrors as &$m) {
        $m['categories'] = getMirrorCategories($pdo, $m['id']);
        $m['protocols'] = getMirrorProtocols($pdo, $m['id']);
    }
    return $mirrors;
}

function getMirrorById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM mirrors WHERE id = ?");
    $stmt->execute([(int)$id]);
    return $stmt->fetch() ?: null;
}

function mirrorPrimaryCategory($pdo, $mirrorId) {
    $cats = getMirrorCategories($pdo, $mirrorId);
    return $cats[0] ?? null;
}

/* ============================================================
   Visits
   ============================================================ */
function recordVisit($pdo) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if ($uri === '' || strpos($uri, '/admin/') !== false) return;
    if (strpos($uri, 'api.php') !== false) return;

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $page = mb_substr(parse_url($uri, PHP_URL_PATH) ?: '/', 0, 255);

    if (empty($ua)) return;

    try {
        $stmt = $pdo->prepare("INSERT INTO visits (ip, user_agent, page, visit_date) VALUES (?, ?, ?, CURDATE())");
        $stmt->execute([$ip, $ua, $page]);
    } catch (Exception $e) {
        // ignore
    }
}

function getVisitStats($pdo) {
    try {
        $today = (int)$pdo->query("SELECT COUNT(*) FROM visits WHERE visit_date = CURDATE()")->fetchColumn();
        $yesterday = (int)$pdo->query("SELECT COUNT(*) FROM visits WHERE visit_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();
        $week = (int)$pdo->query("SELECT COUNT(*) FROM visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
        $month = (int)$pdo->query("SELECT COUNT(*) FROM visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
        $total = (int)$pdo->query("SELECT COUNT(*) FROM visits")->fetchColumn();
        $unique = (int)$pdo->query("SELECT COUNT(DISTINCT ip) FROM visits")->fetchColumn();
        $uniqueToday = (int)$pdo->query("SELECT COUNT(DISTINCT ip) FROM visits WHERE visit_date = CURDATE()")->fetchColumn();

        return compact('today', 'yesterday', 'week', 'month', 'total', 'unique', 'uniqueToday');
    } catch (Exception $e) {
        return ['today' => 0, 'yesterday' => 0, 'week' => 0, 'month' => 0, 'total' => 0, 'unique' => 0, 'uniqueToday' => 0];
    }
}

/* ============================================================
   Slug
   ============================================================ */
function slugify($text) {
    $text = trim((string)$text);
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = strtolower($text);
    $text = preg_replace('~-+~', '-', $text);
    if (empty($text)) {
        return 'n-a-' . substr(md5(uniqid('', true)), 0, 6);
    }
    return $text;
}

/* ============================================================
   Helpers
   ============================================================ */
function isActiveNav($page) {
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    return $current === $page ? 'active' : '';
}

function excerpt($text, $length = 120) {
    $text = trim((string)$text);
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . '...';
}

function formatDate($datetime, $format = 'Y/m/d') {
    return jalaliFormatDate($datetime, (string)$format, true);
}

/**
 * Normalize Persian/Arabic variants and digits before matching user search queries.
 * This is deliberately local and dependency-free.
 */
function normalizeSearchQuery($value, $maxLength = 120) {
    $value = trim((string)$value);
    $value = strtr($value, [
        'ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ۀ' => 'ه', 'ة' => 'ه',
        'ـ' => '', '‌' => ' ',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
    $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    if (function_exists('mb_substr')) $value = mb_substr($value, 0, (int)$maxLength, 'UTF-8');
    else $value = substr($value, 0, (int)$maxLength);
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function currentUrl() {
    return SITE_URL . ($_SERVER['REQUEST_URI'] ?? '');
}

function mirrorDisplayName(array $mirror) {
    $fa = $mirror['name_fa'] ?? '';
    $en = $mirror['name_en'] ?? '';
    if ($fa && $en && $fa !== $en) {
        return ['fa' => $fa, 'en' => $en];
    }
    return ['fa' => $fa ?: $en, 'en' => $en ?: $fa];
}

/* ============================================================
   Client-side Ping
   (زمان پاسخ از سمت مرورگر کاربر اندازه‌گیری می‌شود، نه از سمت سرور)
   ============================================================ */

/**
 * پروتکل‌هایی که مرورگر می‌تواند زمان پاسخ آن‌ها را اندازه بگیرد.
 * مرورگر فقط http/https را می‌تواند درخواست کند؛ ftp/rsync/magnet و... قابل پینگ نیستند.
 */
function isPingableUrl($url) {
    $url = trim((string)$url);
    if ($url === '') return false;
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

/**
 * دکمه پینگ کارت‌ها. آدرس دقیق همان لینکی است که کارت به آن اشاره دارد
 * و اندازه‌گیری کاملاً در مرورگر کاربر (JavaScript) انجام می‌شود.
 * نتیجه (یک عدد ms) روی همین دکمه و رنگی نمایش داده می‌شود.
 * اگر لینک قابل اندازه‌گیری نباشد (پروتکل غیر http/https)، دکمه‌ای ساخته نمی‌شود.
 */
function pingButton($url, $class = '') {
    $url = trim((string)$url);
    if (!isPingableUrl($url)) return '';

    $classes = trim('btn btn-sm btn-outline ping-btn ' . $class);

    return '<button type="button" class="' . htmlspecialchars($classes) . '"'
        . ' data-ping-url="' . htmlspecialchars($url) . '"'
        . ' title="اندازه‌گیری زمان پاسخ این لینک از مرورگر شما (پینگ سمت کاربر)">'
        . icon('activity', 14)
        . '<span class="ping-btn-text">پینگ</span>'
        . '</button>';
}

/* ============================================================
   SVG Icons
   ============================================================ */
function icon($name, $size = 16, $class = '') {
    $icons = [
        'home' => '<path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1V9.5z"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'plus-circle' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
        'dashboard' => '<rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/>',
        'folder' => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
        'server' => '<rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/>',
        'inbox' => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'heart' => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
        'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
        'activity' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        'check' => '<polyline points="20 6 9 17 4 12"/>',
        'x' => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'alert-triangle' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
    ];
    $path = $icons[$name] ?? '';
    $classAttr = $class ? ' class="' . htmlspecialchars($class) . '"' : '';
    return '<svg' . $classAttr . ' width="' . (int)$size . '" height="' . (int)$size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}