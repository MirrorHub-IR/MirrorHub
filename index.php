<?php
require_once __DIR__ . '/includes/installation.php';

$installationStatus = mirrorhubInstallationStatus();
if (in_array($installationStatus, ['not_installed', 'incomplete'], true)) {
    header('Location: ' . mirrorhubSetupUrl(), true, 302);
    exit;
}
if ($installationStatus === 'unavailable') {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Retry-After: 60');
    echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>اتصال پایگاه داده</title><body style="font-family:Tahoma,sans-serif;background:#101712;color:#edf6ee;padding:3rem;line-height:2"><h1>MirrorHub موقتاً در دسترس نیست</h1><p>تنظیمات اتصال پایگاه داده بررسی‌پذیر نیست. دسترسی MySQL/MariaDB و اطلاعات استقرار را بررسی کنید. برای جلوگیری از نصب اشتباه، به نصب‌کننده هدایت نشدید.</p></body></html>';
    exit;
}
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/layout.php';

// Fast overview metrics for the homepage.
$homeStats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM mirrors WHERE status='active') AS mirrors,
        (SELECT COUNT(*) FROM categories WHERE type='software') AS software_categories,
        (SELECT COUNT(*) FROM categories WHERE type='provider') AS providers,
        (SELECT COUNT(*) FROM cms_articles WHERE status='published') AS guides
")->fetch();
$featuredCategories = $pdo->query("
    SELECT c.id,c.name,c.slug,c.description,c.icon,
        (SELECT COUNT(DISTINCT m.id) FROM mirror_categories mc INNER JOIN mirrors m ON m.id=mc.mirror_id AND m.status='active' WHERE mc.category_id=c.id) AS mirror_count
    FROM categories c
    WHERE c.type='software'
    ORDER BY mirror_count DESC,c.sort_order ASC,c.name ASC
    LIMIT 6
")->fetchAll();
$recentMirrors = getRecentMirrors($pdo, 6);
$recentMirrorIds = array_column($recentMirrors, 'id');
$monitorStatuses = monitorStatusMap($pdo, $recentMirrorIds);

$GLOBALS['pageSeo'] = [
    'title' => 'MirrorHub | جست‌وجو و پایش میرورهای نرم‌افزاری',
    'description' => 'میرورهای لینوکس و نرم‌افزارها را پیدا کنید، ارائه‌دهندگان را مرور کنید و وضعیت دسترس‌پذیری مخازن را در MirrorHub ببینید.',
    'canonical' => rtrim(SITE_URL, '/') . '/',
];
renderHeader('خانه');
?>

<section class="home-hero">
    <div class="home-hero-copy">
        <span class="home-hero-kicker"><span class="home-live-dot"></span> فهرست و پایش میرورهای نرم‌افزاری</span>
        <h1>میرور مناسب را <span>سریع‌تر پیدا کنید.</span></h1>
        <p>یک نقطهٔ شروع برای پیدا کردن مخازن، مقایسهٔ ارائه‌دهندگان و بررسی وضعیت دسترسی میرورها.</p>
        <form action="<?= SITE_URL ?>/mirrors/" method="get" class="home-search" role="search" aria-label="جست‌وجوی میرورها">
            <label class="sr-only" for="home-mirror-search">جست‌وجوی میرور</label>
            <span><?= icon('search', 19) ?></span>
            <input id="home-mirror-search" type="search" name="q" placeholder="نام توزیع، نرم‌افزار یا آدرس میرور..." autocomplete="off">
            <button class="btn btn-primary" type="submit">جست‌وجو</button>
        </form>
        <div class="home-hero-links"><a href="<?= SITE_URL ?>/mirrors/"><?= icon('server', 15) ?> مرور همهٔ میرورها</a><a href="<?= SITE_URL ?>/categories/"><?= icon('layers', 15) ?> انتخاب توزیع</a></div>
    </div>
    <div class="home-hero-art" aria-hidden="true">
        <div class="home-orbit home-orbit-one"></div><div class="home-orbit home-orbit-two"></div>
        <div class="home-art-core"><img src="<?= asset('logo.png') ?>" alt="" width="92" height="92"></div>
        <span class="home-art-node home-node-one"><?= icon('server', 18) ?></span><span class="home-art-node home-node-two"><?= icon('activity', 18) ?></span><span class="home-art-node home-node-three"><?= icon('package', 18) ?></span>
    </div>
</section>

<section class="home-stat-strip" aria-label="نمای کلی MirrorHub">
    <div><span class="home-stat-icon"><?= icon('server', 17) ?></span><strong><?= number_format((int)$homeStats['mirrors']) ?></strong><small>میرور فعال</small></div>
    <div><span class="home-stat-icon"><?= icon('layers', 17) ?></span><strong><?= number_format((int)$homeStats['software_categories']) ?></strong><small>دستهٔ نرم‌افزاری</small></div>
    <div><span class="home-stat-icon"><?= icon('network', 17) ?></span><strong><?= number_format((int)$homeStats['providers']) ?></strong><small>ارائه‌دهنده</small></div>
    <div><span class="home-stat-icon"><?= icon('book-open', 17) ?></span><strong><?= number_format((int)$homeStats['guides']) ?></strong><small>مستند منتشرشده</small></div>
</section>

<section class="section home-explore-section">
    <div class="section-heading-home"><div><span class="eyebrow">شروع سریع</span><h2 class="section-title">از کجا می‌خواهید شروع کنید؟</h2></div><p>بخش موردنیاز را مستقیم باز کنید.</p></div>
    <div class="home-explore-grid">
        <a class="home-explore-card home-explore-primary" href="<?= SITE_URL ?>/mirrors/"><span class="home-explore-icon"><?= icon('server', 22) ?></span><strong>همهٔ میرورها</strong><small>جست‌وجو و فیلتر فهرست کامل</small><span class="home-explore-arrow">←</span></a>
        <a class="home-explore-card" href="<?= SITE_URL ?>/categories/"><span class="home-explore-icon"><?= icon('layers', 22) ?></span><strong>دسته‌بندی‌ها</strong><small>توزیع‌ها و نرم‌افزارها</small><span class="home-explore-arrow">←</span></a>
        <a class="home-explore-card" href="<?= SITE_URL ?>/providers/"><span class="home-explore-icon"><?= icon('network', 22) ?></span><strong>ارائه‌دهندگان</strong><small>شبکه‌ها و میزبان‌ها</small><span class="home-explore-arrow">←</span></a>
        <a class="home-explore-card" href="<?= SITE_URL ?>/status/"><span class="home-explore-icon"><?= icon('activity', 22) ?></span><strong>وضعیت میرورها</strong><small>سلامت و دسترس‌پذیری</small><span class="home-explore-arrow">←</span></a>
        <a class="home-explore-card" href="<?= SITE_URL ?>/guides/"><span class="home-explore-icon"><?= icon('book-open', 22) ?></span><strong>مستندات و راهنماها</strong><small>آموزش نصب و تنظیم مخازن</small><span class="home-explore-arrow">←</span></a>
        <a class="home-explore-card" href="<?= SITE_URL ?>/contribute/"><span class="home-explore-icon"><?= icon('users', 22) ?></span><strong>مشارکت</strong><small>معرفی میرور و توسعه پروژه</small><span class="home-explore-arrow">←</span></a>
    </div>
</section>

<?php if ($featuredCategories): ?>
<section class="section home-section">
    <div class="section-heading-home"><div><span class="eyebrow">اکوسیستم نرم‌افزار</span><h2 class="section-title">دسته‌بندی‌های پرمیرور</h2></div><a class="btn btn-outline btn-sm" href="<?= SITE_URL ?>/categories/">همهٔ دسته‌بندی‌ها</a></div>
    <div class="home-featured-categories">
        <?php foreach ($featuredCategories as $category): ?>
        <a class="home-featured-category" href="<?= SITE_URL ?>/category.php?slug=<?= rawurlencode((string)$category['slug']) ?>">
            <span class="home-featured-category-icon"><?php if (!empty($category['icon'])): ?><img src="<?= SITE_URL ?>/uploads/category-icons/<?= htmlspecialchars($category['icon'], ENT_QUOTES, 'UTF-8') ?>" alt="" width="34" height="34" loading="lazy" decoding="async"><?php else: ?><?= icon('package', 20) ?><?php endif; ?></span>
            <span><strong><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= number_format((int)$category['mirror_count']) ?> میرور فعال</small></span>
            <span class="home-featured-arrow">←</span>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($recentMirrors): ?>
<section class="section home-section">
    <div class="section-heading-home"><div><span class="eyebrow">به‌روزرسانی فهرست</span><h2 class="section-title">تازه‌ترین میرورها</h2></div><a class="btn btn-outline btn-sm" href="<?= SITE_URL ?>/mirrors/">نمایش همه</a></div>
    <div class="grid grid-cols-2">
        <?php foreach ($recentMirrors as $mirror): $names = mirrorDisplayName($mirror); ?>
        <article class="card mirror-card">
            <div class="mirror-header"><div class="mirror-titles"><h3><a href="<?= SITE_URL ?>/mirror.php?slug=<?= rawurlencode((string)$mirror['slug']) ?>"><?= htmlspecialchars($names['fa'], ENT_QUOTES, 'UTF-8') ?></a></h3><?php if ($names['en'] && $names['en'] !== $names['fa']): ?><span class="mirror-name-en"><?= htmlspecialchars($names['en'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?></div><div class="mirror-actions"><a href="<?= SITE_URL ?>/mirror.php?slug=<?= rawurlencode((string)$mirror['slug']) ?>" class="btn btn-sm btn-outline">جزئیات</a><a href="<?= htmlspecialchars($mirror['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-primary">بازکردن</a></div></div>
            <?php if ($mirror['description']): ?><p class="mirror-desc"><?= htmlspecialchars(excerpt($mirror['description'], 110), ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            <div class="mirror-meta"><?= monitorCardBadge($monitorStatuses[$mirror['id']] ?? null) ?><?= protocolBadges($mirror['protocols'] ?? []) ?><?php foreach ($mirror['categories'] as $category): ?><a href="<?= SITE_URL ?>/category.php?slug=<?= rawurlencode((string)$category['slug']) ?>" class="badge badge-primary"><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></a><?php endforeach; ?></div>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="home-contribute-banner">
    <div><span class="eyebrow">جامعه‌محور</span><h2>میرور یا اصلاحی برای معرفی دارید؟</h2><p>با ثبت پیشنهاد، اطلاعات را برای بررسی در اختیار تیم MirrorHub قرار دهید.</p></div>
    <a class="btn btn-primary" href="<?= SITE_URL ?>/contribute/">مشارکت در پروژه <?= icon('arrow-left', 15) ?></a>
</section>

<?php renderFooter(); ?>
