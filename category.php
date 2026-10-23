<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/layout.php';

$slug = sanitize($_GET['slug'] ?? '');
if (!$slug) redirect(SITE_URL . '/index.php');

$category = getCategoryBySlug($pdo, $slug);
if (!$category) {
    http_response_code(404);
    $GLOBALS['pageSeo'] = [
        'title' => 'دسته‌بندی پیدا نشد | MirrorHub',
        'description' => 'دسته‌بندی موردنظر پیدا نشد یا دیگر در دسترس نیست.',
        'robots' => 'noindex,follow',
    ];
    renderHeader('دسته‌بندی پیدا نشد');
    echo '<section class="section"><h1 class="section-title">دسته‌بندی پیدا نشد</h1><p class="category-desc">این دسته‌بندی وجود ندارد یا دیگر در دسترس نیست.</p><p><a class="btn btn-primary" href="' . htmlspecialchars(rtrim(SITE_URL, '/') . '/', ENT_QUOTES, 'UTF-8') . '">بازگشت به صفحهٔ اصلی</a></p></section>';
    renderFooter();
    exit;
}

$path = getCategoryPath($pdo, $category['id']);
$categoryDescription = trim(strip_tags((string)($category['description'] ?? '')));
if ($categoryDescription === '') {
    $categoryDescription = 'مشاهدهٔ میرورهای فعال، پروتکل‌ها و راهنماهای مرتبط با دسته‌بندی ' . $category['name'] . ' در MirrorHub.';
}
$GLOBALS['pageSeo'] = [
    'title' => $category['name'] . ' | میرورهای نرم‌افزاری در MirrorHub',
    'description' => mb_substr($categoryDescription, 0, 180),
    'canonical' => rtrim(SITE_URL, '/') . '/category.php?slug=' . rawurlencode($category['slug']),
    'breadcrumbs' => array_merge(
        [['name' => 'خانه', 'url' => rtrim(SITE_URL, '/') . '/']],
        array_map(
            fn($item) => ['name' => (string)$item['name'], 'url' => rtrim(SITE_URL, '/') . '/category.php?slug=' . rawurlencode((string)$item['slug'])],
            $path
        )
    ),
];
$subCategories = getCategories($pdo, $category['id']);
$mirrors = getMirrorsByCategory($pdo, $category['id']);
foreach ($mirrors as &$m) {
    $m['categories'] = getMirrorCategoryTags($pdo, $m['id']);
        $m['protocols'] = getMirrorProtocols($pdo, $m['id']);
}
unset($m);

// وضعیت لحظه‌ای پایش سرورها برای نشان دادن روی کارت‌ها
$monitorStatuses = monitorStatusMap($pdo, array_column($mirrors, 'id'));

// راهنماهای منتشرشده‌ای که مستقیماً به همین دستهٔ نرم‌افزاری وصل شده‌اند.
$relatedGuides = [];
if (($category['type'] ?? '') === 'software') {
    $guideQuery = $pdo->prepare("SELECT title, slug, excerpt, published_at FROM cms_articles
        WHERE category_id = ? AND status = 'published'
        ORDER BY COALESCE(published_at, created_at) DESC, id DESC");
    $guideQuery->execute([(int)$category['id']]);
    $relatedGuides = $guideQuery->fetchAll();
}

renderHeader($category['name']);
?>

<nav class="breadcrumb">
    <a href="<?= SITE_URL ?>/index.php">خانه</a>
    <?php foreach ($path as $index => $p): ?>
        <span class="separator">/</span>
        <?php if ($index === count($path) - 1): ?>
            <span class="current"><?= htmlspecialchars($p['name']) ?></span>
        <?php else: ?>
            <a href="<?= SITE_URL ?>/category.php?slug=<?= htmlspecialchars($p['slug']) ?>"><?= htmlspecialchars($p['name']) ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>

<section class="section">
    <div class="section-header">
        <h1 class="section-title"><?= htmlspecialchars($category['name']) ?></h1>
        <span class="badge badge-primary"><?= count($mirrors) ?> میرور</span>
    </div>
    <?php if ($category['description']): ?>
        <p class="category-desc"><?= htmlspecialchars($category['description']) ?></p>
    <?php endif; ?>
</section>

<?php if (!empty($relatedGuides)): ?>
<link rel="stylesheet" href="<?= asset('css/cms.css') ?>">
<section class="section">
    <div class="section-header">
        <h2 class="section-title">راهنماهای مرتبط با <?= htmlspecialchars($category['name']) ?></h2>
        <span class="badge badge-primary"><?= count($relatedGuides) ?> راهنما</span>
    </div>
    <div class="cms-article-grid">
        <?php foreach ($relatedGuides as $guide): ?>
            <article class="cms-article-card">
                <?php if (!empty($guide['published_at'])): ?>
                    <div class="cms-article-meta"><time><?= htmlspecialchars(formatDate($guide['published_at'])) ?></time></div>
                <?php endif; ?>
                <h3><a href="<?= SITE_URL ?>/guides/<?= rawurlencode($guide['slug']) ?>"><?= htmlspecialchars($guide['title']) ?></a></h3>
                <?php if (!empty($guide['excerpt'])): ?><p><?= htmlspecialchars($guide['excerpt']) ?></p><?php endif; ?>
                <a class="cms-read-more" href="<?= SITE_URL ?>/guides/<?= rawurlencode($guide['slug']) ?>">مطالعه راهنما ←</a>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($subCategories)): ?>
<section class="section">
    <h2 class="section-title">زیردسته‌بندی‌ها</h2>
    <div class="grid grid-cols-3">
        <?php foreach ($subCategories as $subCat): ?>
            <div class="card category-card">
                <a href="<?= SITE_URL ?>/category.php?slug=<?= htmlspecialchars($subCat['slug']) ?>" class="category-card-link">
                    <h3><?= htmlspecialchars($subCat['name']) ?></h3>
                    <?php if ($subCat['description']): ?>
                        <p><?= htmlspecialchars(excerpt($subCat['description'], 80)) ?></p>
                    <?php endif; ?>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <h2 class="section-title">میرورهای این دسته</h2>
    <?php if (empty($mirrors)): ?>
        <p class="empty-state">هنوز میروری در این دسته‌بندی ثبت نشده است.</p>
    <?php else: ?>
        <div class="grid grid-cols-2">
            <?php foreach ($mirrors as $mirror): ?>
                <?php $names = mirrorDisplayName($mirror); ?>
                <div class="card mirror-card">
                    <div class="mirror-header">
                        <div class="mirror-titles">
                            <h3><?= htmlspecialchars($names['fa']) ?></h3>
                            <?php if ($names['en'] && $names['en'] !== $names['fa']): ?>
                                <span class="mirror-name-en"><?= htmlspecialchars($names['en']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="mirror-actions">
                            <a href="<?= htmlspecialchars($mirror['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-primary">مشاهده</a>
                            <?= pingButton($mirror['url']) ?>
                        </div>
                    </div>
                    <?php if ($mirror['description']): ?>
                        <p class="mirror-desc"><?= htmlspecialchars($mirror['description']) ?></p>
                    <?php endif; ?>
                    <div class="mirror-meta">
                        <?= monitorCardBadge($monitorStatuses[$mirror['id']] ?? null) ?>
                        <?= protocolBadges($mirror['protocols'] ?? getMirrorProtocols($pdo, $mirror['id'])) ?>
                        <?php foreach ($mirror['categories'] as $c): ?>
                            <?php if ($c['id'] != $category['id']): ?>
                                <a href="<?= SITE_URL ?>/category.php?slug=<?= htmlspecialchars($c['slug']) ?>" class="badge"><?= htmlspecialchars($c['name']) ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php renderFooter(); ?>