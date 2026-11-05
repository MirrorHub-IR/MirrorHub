<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/monitoring.php';

$query = normalizeSearchQuery($_GET['q'] ?? '', 100);
$categoryId = (isset($_GET['category_id']) && ctype_digit((string)$_GET['category_id'])) ? (int)$_GET['category_id'] : 0;
$statusOptions = ['up', 'slow', 'down', 'unknown'];
$status = in_array((string)($_GET['status'] ?? ''), $statusOptions, true) ? (string)$_GET['status'] : '';
$protocolOptions = getProtocolOptions();
$protocol = array_key_exists((string)($_GET['protocol'] ?? ''), $protocolOptions) ? (string)$_GET['protocol'] : '';

$where = ["m.status = 'active'"];
$params = [];
if ($query !== '') {
    $like = '%' . $query . '%';
    $where[] = '(m.name_fa LIKE ? OR m.name_en LIKE ? OR m.description LIKE ? OR m.url LIKE ?)';
    array_push($params, $like, $like, $like, $like);
}
if ($categoryId > 0) {
    $where[] = 'EXISTS (SELECT 1 FROM mirror_categories fmc WHERE fmc.mirror_id = m.id AND fmc.category_id = ?)';
    $params[] = $categoryId;
}
if ($status !== '') {
    $where[] = "COALESCE((SELECT fs.status FROM mirror_status fs WHERE fs.mirror_id = m.id), 'unknown') = ?";
    $params[] = $status;
}
if ($protocol !== '') {
    if (hasTable($pdo, 'mirror_protocols')) {
        if ($protocol === 'custom') {
            $where[] = "(EXISTS (SELECT 1 FROM mirror_protocols fp WHERE fp.mirror_id = m.id AND fp.protocol = 'custom')
                OR (NOT EXISTS (SELECT 1 FROM mirror_protocols fp0 WHERE fp0.mirror_id = m.id) AND m.protocol IN ('custom','other')))";
        } else {
            $where[] = "(EXISTS (SELECT 1 FROM mirror_protocols fp WHERE fp.mirror_id = m.id AND fp.protocol = ?)
                OR (NOT EXISTS (SELECT 1 FROM mirror_protocols fp0 WHERE fp0.mirror_id = m.id) AND m.protocol = ?))";
            $params[] = $protocol;
            $params[] = $protocol;
        }
    } elseif ($protocol === 'custom') {
        $where[] = "m.protocol IN ('custom','other')";
    } else {
        $where[] = 'm.protocol = ?';
        $params[] = $protocol;
    }
}
$whereSql = implode(' AND ', $where);
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM mirrors m WHERE {$whereSql}");
$countStmt->execute($params);
$totalMirrors = (int)$countStmt->fetchColumn();

$perPage = 24;
$totalPages = max(1, (int)ceil($totalMirrors / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;
$listStmt = $pdo->prepare("SELECT m.* FROM mirrors m WHERE {$whereSql} ORDER BY m.name_fa ASC, m.id DESC LIMIT {$perPage} OFFSET {$offset}");
$listStmt->execute($params);
$mirrors = $listStmt->fetchAll();
$mirrorIds = array_column($mirrors, 'id');
$categoryMap = getMirrorCategoryTagsMap($pdo, $mirrorIds);
$protocolMap = getMirrorProtocolsMap($pdo, $mirrorIds);
$monitorStatuses = monitorStatusMap($pdo, $mirrorIds);
foreach ($mirrors as &$mirror) {
    $id = (int)$mirror['id'];
    $mirror['categories'] = $categoryMap[$id] ?? [];
    $mirror['protocols'] = $protocolMap[$id] ?? getMirrorProtocols($pdo, $id);
}
unset($mirror);

$categories = $pdo->query("SELECT id, name, type FROM categories ORDER BY CASE WHEN type='software' THEN 0 ELSE 1 END, sort_order, name")->fetchAll();
$hasFilters = $query !== '' || $categoryId > 0 || $status !== '' || $protocol !== '';
$GLOBALS['pageSeo'] = [
    'title' => 'همه میرورهای نرم‌افزاری | فهرست و فیلتر MirrorHub',
    'description' => 'فهرست میرورهای فعال نرم‌افزاری را جست‌وجو و بر اساس دسته‌بندی، پروتکل و وضعیت پایش فیلتر کنید.',
    'canonical' => rtrim(SITE_URL, '/') . '/mirrors/',
    'robots' => $hasFilters ? 'noindex,follow' : 'index,follow',
    'breadcrumbs' => [
        ['name' => 'خانه', 'url' => rtrim(SITE_URL, '/') . '/'],
        ['name' => 'همه میرورها', 'url' => rtrim(SITE_URL, '/') . '/mirrors/'],
    ],
];
renderHeader('همه میرورها');
?>
<section class="directory-hero">
    <span class="directory-eyebrow"><?= icon('server', 15) ?> فهرست جامع MirrorHub</span>
    <h1>همهٔ میرورها، <span>یک‌جا</span></h1>
    <p>میرور موردنظر را پیدا کنید و بر اساس دسته‌بندی، پروتکل یا وضعیت پایش محدودش کنید.</p>
    <div class="directory-hero-meta"><strong><?= number_format($totalMirrors) ?></strong><span>میرور مطابق فیلترهای فعلی</span></div>
</section>
<section class="section">
    <form class="directory-filter card" method="get" action="<?= SITE_URL ?>/mirrors/" aria-label="فیلتر میرورها">
        <div class="directory-filter-search"><label for="mirror-query">جست‌وجوی میرور</label><input id="mirror-query" type="search" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" placeholder="نام، توضیح یا نشانی میرور..." autocomplete="off"></div>
        <div><label for="mirror-category">دسته‌بندی</label><select id="mirror-category" name="category_id"><option value="0">همهٔ دسته‌ها</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= $categoryId === (int)$category['id'] ? 'selected' : '' ?>><?= htmlspecialchars(($category['type'] === 'provider' ? 'ارائه‌دهنده · ' : 'نرم‌افزار · ') . $category['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
        <div><label for="mirror-status">وضعیت پایش</label><select id="mirror-status" name="status"><option value="">همه وضعیت‌ها</option><option value="up" <?= $status === 'up' ? 'selected' : '' ?>>آنلاین</option><option value="slow" <?= $status === 'slow' ? 'selected' : '' ?>>کند</option><option value="down" <?= $status === 'down' ? 'selected' : '' ?>>قطع</option><option value="unknown" <?= $status === 'unknown' ? 'selected' : '' ?>>بررسی‌نشده</option></select></div>
        <div><label for="mirror-protocol">پروتکل</label><select id="mirror-protocol" name="protocol"><option value="">همهٔ پروتکل‌ها</option><?php foreach ($protocolOptions as $key => $label): ?><option value="<?= htmlspecialchars($key) ?>" <?= $protocol === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></div>
        <div class="directory-filter-actions"><button class="btn btn-primary" type="submit"><?= icon('search', 15) ?> اعمال فیلتر</button><a class="btn btn-outline" href="<?= SITE_URL ?>/mirrors/">پاک‌کردن</a></div>
    </form>
</section>
<section class="section directory-results">
    <div class="section-header"><div><span class="eyebrow">فهرست میرورها</span><h2 class="section-title">نتایج</h2></div><span class="badge badge-primary"><?= number_format($totalMirrors) ?> مورد</span></div>
    <?php if (!$mirrors): ?>
        <div class="directory-empty"><span><?= icon('search-x', 28) ?></span><h2>میروری پیدا نشد</h2><p>فیلترها را تغییر دهید یا عبارت کوتاه‌تری جست‌وجو کنید.</p><a class="btn btn-outline" href="<?= SITE_URL ?>/mirrors/">نمایش همهٔ میرورها</a></div>
    <?php else: ?>
        <div class="grid grid-cols-2">
            <?php foreach ($mirrors as $mirror): $names = mirrorDisplayName($mirror); ?>
            <article class="card mirror-card">
                <div class="mirror-header"><div class="mirror-titles"><h3><a href="<?= SITE_URL ?>/mirror.php?slug=<?= rawurlencode((string)$mirror['slug']) ?>"><?= htmlspecialchars($names['fa'], ENT_QUOTES, 'UTF-8') ?></a></h3><?php if ($names['en'] && $names['en'] !== $names['fa']): ?><span class="mirror-name-en"><?= htmlspecialchars($names['en'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?></div><div class="mirror-actions"><a class="btn btn-sm btn-outline" href="<?= SITE_URL ?>/mirror.php?slug=<?= rawurlencode((string)$mirror['slug']) ?>">جزئیات</a><a class="btn btn-sm btn-primary" href="<?= htmlspecialchars($mirror['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">بازکردن</a></div></div>
                <?php if (!empty($mirror['description'])): ?><p class="mirror-desc"><?= htmlspecialchars(excerpt($mirror['description'], 130), ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                <div class="mirror-meta"><?= monitorCardBadge($monitorStatuses[$mirror['id']] ?? null) ?><?= protocolBadges($mirror['protocols']) ?><?php foreach ($mirror['categories'] as $category): ?><a class="badge badge-primary" href="<?= SITE_URL ?>/category.php?slug=<?= rawurlencode((string)$category['slug']) ?>"><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></a><?php endforeach; ?></div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php if ($totalPages > 1): $queryParams = array_filter(['q' => $query, 'category_id' => $categoryId ?: null, 'status' => $status, 'protocol' => $protocol], static fn($value) => $value !== null && $value !== ''); ?>
        <nav class="directory-pagination" aria-label="صفحه‌بندی میرورها">
            <?php if ($page > 1): ?><a class="btn btn-outline" href="<?= SITE_URL ?>/mirrors/?<?= htmlspecialchars(http_build_query($queryParams + ['page' => $page - 1]), ENT_QUOTES, 'UTF-8') ?>">صفحهٔ قبل</a><?php endif; ?>
            <span>صفحهٔ <?= number_format($page) ?> از <?= number_format($totalPages) ?></span>
            <?php if ($page < $totalPages): ?><a class="btn btn-outline" href="<?= SITE_URL ?>/mirrors/?<?= htmlspecialchars(http_build_query($queryParams + ['page' => $page + 1]), ENT_QUOTES, 'UTF-8') ?>">صفحهٔ بعد</a><?php endif; ?>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php renderFooter(); ?>
