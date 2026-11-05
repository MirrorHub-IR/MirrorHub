<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

$categories = $pdo->query("
    SELECT c.id,c.name,c.slug,c.description,c.icon,p.name AS parent_name,
        (SELECT COUNT(DISTINCT m.id) FROM mirror_categories mc INNER JOIN mirrors m ON m.id=mc.mirror_id AND m.status='active' WHERE mc.category_id=c.id) AS mirror_count
    FROM categories c
    LEFT JOIN categories p ON p.id=c.parent_id
    WHERE c.type='software'
    ORDER BY c.parent_id IS NULL DESC,c.sort_order ASC,c.name ASC
")->fetchAll();
$GLOBALS['pageSeo'] = [
    'title' => 'دسته‌بندی نرم‌افزارها و توزیع‌ها | MirrorHub',
    'description' => 'دسته‌بندی توزیع‌های لینوکس و نرم‌افزارها را مرور کنید و به فهرست میرورهای هر دسته دسترسی داشته باشید.',
    'canonical' => rtrim(SITE_URL, '/') . '/categories/',
    'breadcrumbs' => [
        ['name' => 'خانه', 'url' => rtrim(SITE_URL, '/') . '/'],
        ['name' => 'دسته‌بندی نرم‌افزارها', 'url' => rtrim(SITE_URL, '/') . '/categories/'],
    ],
];
renderHeader('دسته‌بندی نرم‌افزارها');
?>
<section class="directory-hero directory-hero-compact">
    <span class="directory-eyebrow"><?= icon('layers', 15) ?> دسته‌بندی‌ها</span>
    <h1>توزیع‌ها و <span>نرم‌افزارها</span></h1>
    <p>دستهٔ موردنظر را انتخاب کنید تا میرورها، پروتکل‌ها و راهنماهای مرتبط را ببینید.</p>
    <div class="directory-hero-meta"><strong><?= number_format(count($categories)) ?></strong><span>دستهٔ نرم‌افزاری ثبت‌شده</span></div>
</section>
<section class="section">
    <div class="section-header"><h2 class="section-title">همهٔ دسته‌بندی‌ها</h2><a class="btn btn-outline btn-sm" href="<?= SITE_URL ?>/mirrors/">مشاهدهٔ همهٔ میرورها</a></div>
    <?php if (!$categories): ?><p class="empty-state">هنوز دسته‌بندی نرم‌افزاری ثبت نشده است.</p><?php else: ?>
    <div class="grid grid-cols-3 directory-category-grid">
        <?php foreach ($categories as $category): ?>
        <a class="card directory-category-card" href="<?= SITE_URL ?>/category.php?slug=<?= rawurlencode((string)$category['slug']) ?>">
            <span class="directory-category-icon"><?php if (!empty($category['icon'])): ?><img src="<?= SITE_URL ?>/uploads/category-icons/<?= htmlspecialchars($category['icon'], ENT_QUOTES, 'UTF-8') ?>" alt="" width="38" height="38" loading="lazy" decoding="async"><?php else: ?><?= icon('package', 21) ?><?php endif; ?></span>
            <?php if (!empty($category['parent_name'])): ?><small><?= htmlspecialchars($category['parent_name'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
            <strong><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></strong>
            <?php if (!empty($category['description'])): ?><p><?= htmlspecialchars(excerpt($category['description'], 95), ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            <span class="directory-category-count"><?= number_format((int)$category['mirror_count']) ?> میرور <span aria-hidden="true">←</span></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<?php renderFooter(); ?>
