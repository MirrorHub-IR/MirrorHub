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
$recentIds = array_column($recentMirrors, 'id');
$recentCategoryMap = getMirrorCategoryTagsMap($pdo, $recentIds);
$recentProtocolMap = getMirrorProtocolsMap($pdo, $recentIds);
foreach ($recentMirrors as &$mirror) {
    $id = (int)$mirror['id'];
    $mirror['categories'] = $recentCategoryMap[$id] ?? [];
    $mirror['protocols'] = $recentProtocolMap[$id] ?? [];
}
unset($mirror);

// وضعیت لحظه‌ای پایش سرورها برای نشان دادن روی کارت‌ها
$monitorStatuses = monitorStatusMap($pdo, $recentIds);

$GLOBALS['pageSeo'] = [
    'title' => 'MirrorHub | جست‌وجوی میرورهای نرم‌افزاری و مخازن',
    'description' => 'در MirrorHub میرورهای نرم‌افزاری و مخازن را پیدا کنید، دسته‌بندی‌ها را مرور کنید و وضعیت ثبت‌شدهٔ سرویس‌ها و راهنماهای راه‌اندازی را ببینید.',
    'canonical' => rtrim(SITE_URL, '/') . '/',
];

renderHeader('خانه');
?>

<section class="hero">
    <span class="hero-kicker">MirrorHub · فهرست میرورها</span>
    <h1>میرورهای <span>نرم‌افزاری</span> را آسان‌تر پیدا کنید</h1>
    <p>جست‌وجوی مخازن و ارائه‌دهندگان، مرور دسته‌بندی‌ها و مشاهدهٔ وضعیت ثبت‌شدهٔ میرورها در یک جا.</p>
    <form action="<?= SITE_URL ?>/search.php" method="GET" class="search-form" role="search" aria-label="جست‌وجوی میرورها">
        <input type="search" name="q" aria-label="نام، توضیحات یا آدرس میرور" placeholder="مثلاً Ubuntu، Debian یا آدرس میرور..." required autocomplete="off">
        <button type="submit">جست‌وجو</button>
    </form>
    <p class="search-shortcut"><kbd>/</kbd> برای رفتن سریع به کادر جست‌وجو</p>
</section>

<?php if (!empty($softwareCategories) || !empty($providerCategories)): ?>
<section class="category-filter-bar" hidden aria-label="پیدا کردن دسته‌بندی">
    <label for="category-filter">دنبال کدام دسته‌بندی هستید؟</label>
    <input id="category-filter" type="search" placeholder="نام نرم‌افزار یا ارائه‌دهنده را بنویسید..." autocomplete="off" >
    <p id="category-filter-status" role="status" aria-live="polite">برای پیدا کردن سریع‌تر دسته‌بندی، نام آن را بنویسید.</p>
    <p id="category-filter-empty" class="empty-state" hidden>دسته‌بندی‌ای با این عبارت پیدا نشد.</p>
</section>
<?php endif; ?>

<?php if (!empty($softwareCategories)): ?>
<section class="section category-list-section" id="software-categories" data-category-section>
    <div class="section-header">
        <h2 class="section-title">نرم‌افزارها</h2>
        <span class="badge badge-primary" data-category-count><?= count($softwareCategories) ?> دسته‌بندی</span>
    </div>
    <div class="grid grid-cols-3">
        <?php foreach ($softwareCategories as $cat): ?>
            <div class="card category-card" data-category-card data-search="<?= htmlspecialchars($cat['name'] . ' ' . $cat['slug'] . ' ' . ($cat['description'] ?? '') . ' ' . ($cat['parent_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
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
<section class="section category-list-section" id="provider-categories" data-category-section>
    <div class="section-header">
        <h2 class="section-title">ارائه‌دهندگان</h2>
        <span class="badge badge-primary" data-category-count><?= count($providerCategories) ?> دسته‌بندی</span>
    </div>
    <div class="grid grid-cols-3">
        <?php foreach ($providerCategories as $cat): ?>
            <div class="card category-card" data-category-card data-search="<?= htmlspecialchars($cat['name'] . ' ' . $cat['slug'] . ' ' . ($cat['description'] ?? '') . ' ' . ($cat['parent_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
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