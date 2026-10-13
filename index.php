<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/layout.php';

// Get software categories
$softwareCategories = $pdo->query(
    "SELECT c.*, p.name AS parent_name, p.slug AS parent_slug
    FROM categories c
    LEFT JOIN categories p ON p.id = c.parent_id
    WHERE c.type = 'software'
    ORDER BY c.parent_id IS NULL DESC, c.sort_order ASC, c.name ASC"
)->fetchAll();

// Get provider categories
$providerCategories = $pdo->query(
    "SELECT c.*, p.name AS parent_name, p.slug AS parent_slug
    FROM categories c
    LEFT JOIN categories p ON p.id = c.parent_id
    WHERE c.type = 'provider'
    ORDER BY c.parent_id IS NULL DESC, c.sort_order ASC, c.name ASC"
)->fetchAll();

$stmt = $pdo->query("SELECT * FROM mirrors WHERE status = 'active' ORDER BY created_at DESC LIMIT 12");
$recentMirrors = $stmt->fetchAll();
foreach ($recentMirrors as &$mirror) {
    $mirror['categories'] = getMirrorCategoryTags($pdo, $mirror['id']);
    $mirror['protocols'] = getMirrorProtocols($pdo, $mirror['id']);
}
unset($mirror);

// وضعیت لحظه‌ای پایش سرورها برای نشان دادن روی کارت‌ها
$monitorStatuses = monitorStatusMap($pdo, array_column($recentMirrors, 'id'));

renderHeader('خانه');
?>

<section class="hero">
    <h1>مخزن <span>میرورهای نرم‌افزاری</span></h1>
    <p>دسترسی سریع و آسان به آخرین نسخه‌های نرم‌افزارها و بسته‌ها</p>
    <form action="<?= SITE_URL ?>/search.php" method="GET" class="search-form">
        <input type="text" name="q" placeholder="جستجوی میرور..." required autocomplete="off">
        <button type="submit">جستجو</button>
    </form>
</section>

<?php if (!empty($softwareCategories)): ?>
<section class="section">
    <div class="section-header">
        <h2 class="section-title">نرم‌افزارها</h2>
        <span class="badge badge-primary"><?= count($softwareCategories) ?> دسته‌بندی</span>
    </div>
    <div class="grid grid-cols-3">
        <?php foreach ($softwareCategories as $cat): ?>
            <div class="card category-card">
                <a href="<?= SITE_URL ?>/category.php?slug=<?= htmlspecialchars($cat['slug']) ?>" class="category-card-link">
                    <?php if (!empty($cat['icon'])): ?>
                        <img src="<?= SITE_URL ?>/uploads/category-icons/<?= htmlspecialchars($cat['icon']) ?>" alt="" class="category-card-icon">
                    <?php endif; ?>
                    <?php if ($cat['parent_name']): ?>
                        <span class="category-parent-tag"><?= htmlspecialchars($cat['parent_name']) ?></span>
                    <?php endif; ?>
                    <h3><?= htmlspecialchars($cat['name']) ?></h3>
                    <?php if ($cat['description']): ?>
                        <p><?= htmlspecialchars(excerpt($cat['description'], 80)) ?></p>
                    <?php endif; ?>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($providerCategories)): ?>
<section class="section">
    <div class="section-header">
        <h2 class="section-title">ارائه‌دهندگان</h2>
        <span class="badge badge-primary"><?= count($providerCategories) ?> دسته‌بندی</span>
    </div>
    <div class="grid grid-cols-3">
        <?php foreach ($providerCategories as $cat): ?>
            <div class="card category-card">
                <a href="<?= SITE_URL ?>/category.php?slug=<?= htmlspecialchars($cat['slug']) ?>" class="category-card-link">
                    <?php if (!empty($cat['icon'])): ?>
                        <img src="<?= SITE_URL ?>/uploads/category-icons/<?= htmlspecialchars($cat['icon']) ?>" alt="" class="category-card-icon">
                    <?php endif; ?>
                    <?php if ($cat['parent_name']): ?>
                        <span class="category-parent-tag"><?= htmlspecialchars($cat['parent_name']) ?></span>
                    <?php endif; ?>
                    <h3><?= htmlspecialchars($cat['name']) ?></h3>
                    <?php if ($cat['description']): ?>
                        <p><?= htmlspecialchars(excerpt($cat['description'], 80)) ?></p>
                    <?php endif; ?>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (empty($softwareCategories) && empty($providerCategories)): ?>
<section class="section">
    <p class="empty-state">هنوز دسته‌بندی‌ای ایجاد نشده است.</p>
</section>
<?php endif; ?>

<?php if (!empty($recentMirrors)): ?>
<section class="section">
    <div class="section-header">
        <h2 class="section-title">آخرین میرورهای اضافه شده</h2>
        <a href="<?= SITE_URL ?>/search.php" class="btn btn-sm btn-outline">مشاهده همه</a>
    </div>
    <div class="grid grid-cols-2">
        <?php foreach ($recentMirrors as $mirror): ?>
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
                    <p class="mirror-desc"><?= htmlspecialchars(excerpt($mirror['description'], 120)) ?></p>
                <?php endif; ?>
                <div class="mirror-meta">
                    <?= monitorCardBadge($monitorStatuses[$mirror['id']] ?? null) ?>
                    <?= protocolBadges($mirror['protocols'] ?? getMirrorProtocols($pdo, $mirror['id'])) ?>
                    <?php foreach ($mirror['categories'] as $c): ?>
                        <a href="<?= SITE_URL ?>/category.php?slug=<?= htmlspecialchars($c['slug']) ?>" class="badge badge-primary"><?= htmlspecialchars($c['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php renderFooter(); ?>