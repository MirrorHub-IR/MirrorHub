<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

$providers = $pdo->query("
    SELECT c.id,c.name,c.slug,c.description,c.icon,p.name AS parent_name,
        (SELECT COUNT(DISTINCT m.id) FROM mirror_categories mc INNER JOIN mirrors m ON m.id=mc.mirror_id AND m.status='active' WHERE mc.category_id=c.id) AS mirror_count
    FROM categories c
    LEFT JOIN categories p ON p.id=c.parent_id
    WHERE c.type='provider'
    ORDER BY c.parent_id IS NULL DESC,c.sort_order ASC,c.name ASC
")->fetchAll();
$GLOBALS['pageSeo'] = [
    'title' => 'ارائه‌دهندگان میرور و سرویس‌های میزبانی | MirrorHub',
    'description' => 'ارائه‌دهندگان و شبکه‌های میزبانی میرورها را مرور کنید و فهرست سرورهای هر ارائه‌دهنده را ببینید.',
    'canonical' => rtrim(SITE_URL, '/') . '/providers/',
    'breadcrumbs' => [
        ['name' => 'خانه', 'url' => rtrim(SITE_URL, '/') . '/'],
        ['name' => 'ارائه‌دهندگان', 'url' => rtrim(SITE_URL, '/') . '/providers/'],
    ],
];
renderHeader('ارائه‌دهندگان');
?>
<section class="directory-hero directory-hero-compact">
    <span class="directory-eyebrow"><?= icon('network', 15) ?> شبکهٔ ارائه‌دهندگان</span>
    <h1>ارائه‌دهندگان <span>میرورها</span></h1>
    <p>شبکه‌ها و میزبان‌هایی را پیدا کنید که زیرساخت توزیع میرورهای نرم‌افزاری را فراهم می‌کنند.</p>
    <div class="directory-hero-meta"><strong><?= number_format(count($providers)) ?></strong><span>ارائه‌دهنده و دستهٔ میزبانی</span></div>
</section>
<section class="section">
    <div class="section-header"><h2 class="section-title">فهرست ارائه‌دهندگان</h2><a class="btn btn-outline btn-sm" href="<?= SITE_URL ?>/mirrors/">فهرست همهٔ میرورها</a></div>
    <?php if (!$providers): ?><p class="empty-state">هنوز ارائه‌دهنده‌ای ثبت نشده است.</p><?php else: ?>
    <div class="grid grid-cols-3 directory-category-grid">
        <?php foreach ($providers as $provider): ?>
        <a class="card directory-category-card" href="<?= SITE_URL ?>/category.php?slug=<?= rawurlencode((string)$provider['slug']) ?>">
            <span class="directory-category-icon"><?php if (!empty($provider['icon'])): ?><img src="<?= SITE_URL ?>/uploads/category-icons/<?= htmlspecialchars($provider['icon'], ENT_QUOTES, 'UTF-8') ?>" alt="" width="38" height="38" loading="lazy" decoding="async"><?php else: ?><?= icon('building-2', 21) ?><?php endif; ?></span>
            <?php if (!empty($provider['parent_name'])): ?><small><?= htmlspecialchars($provider['parent_name'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
            <strong><?= htmlspecialchars($provider['name'], ENT_QUOTES, 'UTF-8') ?></strong>
            <?php if (!empty($provider['description'])): ?><p><?= htmlspecialchars(excerpt($provider['description'], 95), ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            <span class="directory-category-count"><?= number_format((int)$provider['mirror_count']) ?> میرور <span aria-hidden="true">←</span></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<?php renderFooter(); ?>
